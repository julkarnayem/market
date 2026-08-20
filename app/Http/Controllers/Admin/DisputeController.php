<?php
namespace App\Http\Controllers\Admin;

use App\Enums\DisputeReason;
use App\Enums\DisputeStatus;
use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\DisputeEvidence;
use App\Models\DisputeResolution;
use App\Models\Message;
use App\Services\DisputeService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/**
 * The admin dispute queue and the decisions taken from it.
 *
 * Reading the queue needs the `admin` middleware only; every action that moves
 * money or writes to the thread authorizes `disputes.manage`. The money itself is
 * DisputeService's business — this controller validates, converts taka to poisha,
 * and hands over.
 */
class DisputeController extends Controller
{
    public function __construct(private readonly DisputeService $service) {}

    public function index()
    {
        $status   = (string) request('status', 'open');
        $search   = trim((string) request('q', ''));

        $disputes = Dispute::query()
            // The "Resolved" tab stands for the three resolved_* statuses; every
            // other tab is the status itself.
            ->when($status === 'resolved', fn ($q) => $q->whereIn('status', DisputeStatus::resolvedValues()))
            ->when(
                $status !== 'all' && $status !== 'resolved',
                fn ($q) => $q->where('status', $status),
            )
            ->when($search !== '', fn ($q) => $q
                ->where(fn ($w) => $w
                    ->where('reference', 'like', "%{$search}%")
                    ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$search}%"))))
            ->with('order.buyer', 'order.seller', 'opener')
            // Newest activity first — a dispute waiting on staff should not sink
            // below one that was merely opened later.
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Disputes/Index', [
            'disputes' => $disputes->through(fn (Dispute $d) => [
                'id'           => $d->id,
                'reference'    => $d->displayReference(),
                'order_number' => $d->order?->order_number ?? '—',
                'order_url'    => $d->order ? route('admin.orders.show', $d->order) : null,
                'buyer'        => $d->order?->buyer?->name ?? '—',
                'seller'       => $d->order?->seller?->name ?? '—',
                'order_total'  => Money::format($d->order?->buyer_total ?? 0),
                'reason'       => $d->reason_code?->label() ?? '—',
                'status'       => $d->status->value,
                'status_label' => $d->status->label(),
                'is_escalated' => $d->status->isEscalated(),
                'opened'       => $d->created_at->format('d M Y'),
                'activity'     => $d->last_activity_at?->diffForHumans() ?? '—',
                'url'          => route('admin.disputes.show', $d),
            ]),
            'filters' => [
                'status' => $status,
                'q'      => $search,
            ],
            'tabs' => DisputeStatus::adminTabs(),
        ]);
    }

    public function show(Dispute $dispute)
    {
        $dispute->load(
            'order.buyer', 'order.seller', 'order.asset', 'opener', 'resolver',
            'evidence.submitter', 'order.conversation.messages.sender',
        );
        $order   = $dispute->order;
        $pending = $dispute->pendingResolution();

        return Inertia::render('Admin/Disputes/Show', [
            'dispute' => [
                'id'                => $dispute->id,
                'reference'         => $dispute->displayReference(),
                'status'            => $dispute->status->value,
                'status_label'      => $dispute->status->label(),
                // Whether a decision can still be applied — the Vue swaps the
                // resolution panel for a read-only summary on the strength of it.
                'is_active'         => $dispute->isActive(),
                'is_escalated'      => $dispute->status->isEscalated(),
                'reason'            => $dispute->reason_code?->label() ?? '—',
                'reason_code'       => $dispute->reason_code?->value,
                'description'       => $dispute->description,
                'opener'            => $dispute->opener?->name ?? '—',
                'opened'            => $dispute->created_at?->format('d M Y, H:i'),
                'seller_responded'  => $dispute->seller_responded_at?->format('d M Y, H:i'),
                'escalated_at'      => $dispute->escalated_at?->format('d M Y, H:i'),
                'resolution_type'   => $dispute->resolution_type
                    ? ucwords(str_replace('_', ' ', $dispute->resolution_type)) : null,
                'resolution_amount' => $dispute->resolution_amount ? Money::format($dispute->resolution_amount) : null,
                'resolution_note'   => $dispute->resolution_note,
                'resolver'          => $dispute->resolver?->name ?? '—',
                'resolved_at'       => $dispute->resolved_at?->format('d M Y, H:i') ?? '—',
            ],
            'order' => [
                'number'          => $order?->order_number ?? '—',
                'url'             => $order ? route('admin.orders.show', $order) : null,
                'buyer'           => $order?->buyer?->name ?? '—',
                'seller'          => $order?->seller?->name ?? '—',
                'asset_title'     => $order?->asset?->title ?? '—',
                'buyer_total'     => Money::format($order?->buyer_total ?? 0),
                // Raw BDT drives the partial-refund input's max; server owns the ceiling.
                'buyer_total_bdt' => Money::toBdt($order?->buyer_total ?? 0),
                'seller_earning'  => Money::format($order?->seller_earning ?? 0),
            ],
            // Admins see the whole thread, internal notes included — threadFor()
            // is what decides that, from the viewer.
            'thread' => $this->service->threadFor($dispute, Auth::user()),
            'evidence' => $dispute->evidence->map(fn (DisputeEvidence $ev) => [
                'id'        => $ev->id,
                'role'      => ucfirst((string) $ev->role),
                'is_buyer'  => $ev->role === 'buyer',
                'submitter' => $ev->submitter?->name ?? '—',
                'created'   => $ev->created_at->format('d M, H:i'),
                'message'   => $ev->message,
                'has_file'  => $ev->hasFile(),
                'file_name' => $ev->file_original_name,
                'file_size' => $ev->sizeLabel(),
                'is_image'  => $ev->isImage(),
                'url'       => $ev->hasFile()
                    ? route('dashboard.disputes.evidence', [$dispute->id, $ev->id])
                    : null,
            ])->all(),
            'pending' => $pending ? [
                'id'         => $pending->id,
                'type_label' => $pending->type->label(),
                'amount'     => $pending->amount ? Money::format($pending->amount) : null,
                'note'       => $pending->note,
                'proposer'   => $pending->proposer?->name ?? '—',
                'role'       => $pending->role,
                'awaiting'   => $pending->awaitingRole(),
            ] : null,
            'resolutions' => $dispute->resolutions()->with('proposer', 'responder')->get()
                ->map(fn (DisputeResolution $r) => [
                    'id'         => $r->id,
                    'type_label' => $r->type->label(),
                    'amount'     => $r->amount ? Money::format($r->amount) : null,
                    'role'       => $r->role,
                    'proposer'   => $r->proposer?->name ?? '—',
                    'status'     => $r->status,
                    'executed'   => $r->isExecuted(),
                    'note'       => $r->note,
                    'at'         => $r->created_at?->format('d M Y, H:i'),
                ])->all(),
            'messages' => $order?->conversation
                ? $order->conversation->messages->sortBy('created_at')->take(10)->map(fn (Message $m) => [
                    'id'     => $m->id,
                    'sender' => $m->sender?->name ?? '—',
                    'body'   => $m->body,
                ])->values()->all()
                : [],
            'reasons' => DisputeReason::options(),
        ]);
    }

    public function fullRefund(Request $request, Dispute $dispute)
    {
        $this->authorize('disputes.manage');
        $data = $request->validate(['note' => 'nullable|string|max:1000']);
        $this->service->resolveFullRefund($dispute, Auth::user(), $data['note'] ?? '');
        return back()->with('success', 'Full refund processed. Buyer has been credited.');
    }

    public function partialRefund(Request $request, Dispute $dispute)
    {
        $this->authorize('disputes.manage');
        $data = $request->validate([
            'refund_bdt' => 'required|numeric|min:0.01',
            'note'       => 'nullable|string|max:1000',
        ]);
        $refundPoisha = Money::toPoisha($data['refund_bdt']);
        $this->service->resolvePartialRefund($dispute, Auth::user(), $refundPoisha, $data['note'] ?? '');
        return back()->with('success', 'Partial refund (' . Money::format($refundPoisha) . ') processed.');
    }

    public function releaseToSeller(Request $request, Dispute $dispute)
    {
        $this->authorize('disputes.manage');
        $data = $request->validate(['note' => 'nullable|string|max:1000']);
        $this->service->resolveSellerRelease($dispute, Auth::user(), $data['note'] ?? '');
        return back()->with('success', 'Seller earning released. Order marked as completed.');
    }

    /** No money moves — the seller owes a re-delivery instead. */
    public function replacement(Request $request, Dispute $dispute)
    {
        $this->authorize('disputes.manage');
        $data = $request->validate(['note' => 'required|string|max:1000']);
        $this->service->resolveReplacement($dispute, Auth::user(), $data['note']);
        return back()->with('success', 'Replacement ordered. The order is back to awaiting delivery.');
    }

    /** Close a dispute with no financial outcome. */
    public function close(Request $request, Dispute $dispute)
    {
        $this->authorize('disputes.manage');
        $data = $request->validate(['note' => 'required|string|max:1000']);
        $this->service->cancel($dispute, Auth::user(), $data['note']);
        return back()->with('success', 'Dispute closed with no refund.');
    }

    /** Take ownership of a dispute the parties have not settled. */
    public function escalate(Request $request, Dispute $dispute)
    {
        $this->authorize('disputes.manage');
        $data = $request->validate(['note' => 'nullable|string|max:1000']);
        $this->service->escalate($dispute, Auth::user(), $data['note'] ?? '');
        return back()->with('success', 'Dispute escalated.');
    }

    /** A message from staff, visible to both parties. */
    public function message(Request $request, Dispute $dispute)
    {
        $this->authorize('disputes.manage');
        $data = $request->validate(['body' => 'required|string|min:2|max:5000']);
        $this->service->postMessage($dispute, Auth::user(), $data['body']);
        return back()->with('success', 'Message posted to the dispute.');
    }

    /** Staff-only commentary. Never rendered outside this screen. */
    public function internalNote(Request $request, Dispute $dispute)
    {
        $this->authorize('disputes.manage');
        $data = $request->validate(['body' => 'required|string|min:2|max:5000']);
        $this->service->addInternalNote($dispute, Auth::user(), $data['body']);
        return back()->with('success', 'Internal note saved.');
    }

    /** Ask a named side for more evidence. */
    public function requestEvidence(Request $request, Dispute $dispute)
    {
        $this->authorize('disputes.manage');
        $data = $request->validate([
            'from' => 'required|in:buyer,seller,both',
            'note' => 'nullable|string|max:1000',
        ]);
        $this->service->requestEvidence($dispute, Auth::user(), $data['from'], $data['note'] ?? '');
        return back()->with('success', 'Evidence requested.');
    }
}
