<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\DisputeEvidence;
use App\Models\Message;
use App\Services\DisputeService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DisputeController extends Controller
{
    public function __construct(private readonly DisputeService $service) {}

    public function index()
    {
        $status   = request('status', 'open');
        $disputes = Dispute::query()
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->with('order.buyer', 'order.seller', 'opener')
            ->latest()->paginate(20)->withQueryString();

        return Inertia::render('Admin/Disputes/Index', [
            'disputes' => $disputes->through(fn (Dispute $d) => [
                'id'           => $d->id,
                'order_number' => $d->order?->order_number ?? '—',
                'order_url'    => $d->order ? route('admin.orders.show', $d->order) : null,
                'buyer'        => $d->order?->buyer?->name ?? '—',
                'seller'       => $d->order?->seller?->name ?? '—',
                'order_total'  => Money::format($d->order?->buyer_total ?? 0),
                'status'       => $d->status->value,
                'status_label' => $d->status->label(),
                'opened'       => $d->created_at->format('d M Y'),
                'url'          => route('admin.disputes.show', $d),
            ]),
            'filters' => [
                'status' => $status,
            ],
            'tabs' => $this->statusTabs(),
        ]);
    }

    public function show(Dispute $dispute)
    {
        $dispute->load(
            'order.buyer', 'order.seller', 'order.asset', 'opener', 'resolver',
            'evidence.submitter', 'order.conversation.messages.sender',
        );
        $order = $dispute->order;

        return Inertia::render('Admin/Disputes/Show', [
            'dispute' => [
                'id'                => $dispute->id,
                'status'            => $dispute->status->value,
                'status_label'      => $dispute->status->label(),
                'is_open'           => $dispute->status->isOpen(),
                'reason'            => $dispute->reason,
                'opener'            => $dispute->opener?->name ?? '—',
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
                'buyer_total'     => Money::format($order?->buyer_total ?? 0),
                // Raw BDT drives the partial-refund input's max; server owns the ceiling.
                'buyer_total_bdt' => Money::toBdt($order?->buyer_total ?? 0),
                'seller_earning'  => Money::format($order?->seller_earning ?? 0),
            ],
            'evidence' => $dispute->evidence->map(fn (DisputeEvidence $ev) => [
                'id'        => $ev->id,
                'role'      => ucfirst($ev->role),
                'is_buyer'  => $ev->role === 'buyer',
                'submitter' => $ev->submitter?->name ?? '—',
                'created'   => $ev->created_at->format('d M, H:i'),
                'message'   => $ev->message,
                'has_file'  => $ev->hasFile(),
                'file_name' => $ev->file_original_name,
            ])->all(),
            'messages' => $order?->conversation
                ? $order->conversation->messages->sortBy('created_at')->take(10)->map(fn (Message $m) => [
                    'id'     => $m->id,
                    'sender' => $m->sender?->name ?? '—',
                    'body'   => $m->body,
                ])->values()->all()
                : [],
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

    public function updateStatus(Request $request, Dispute $dispute)
    {
        $this->authorize('disputes.manage');
        $data = $request->validate([
            'status' => 'required|in:under_review,waiting_for_buyer,waiting_for_seller',
            'note'   => 'nullable|string|max:500',
        ]);
        $this->service->updateStatus($dispute, Auth::user(), $data['status'], $data['note'] ?? '');
        return back()->with('success', 'Dispute status updated.');
    }

    /** @return list<array{value:string,label:string}> */
    private function statusTabs(): array
    {
        return [
            ['value' => 'open',               'label' => 'Open'],
            ['value' => 'under_review',       'label' => 'Under Review'],
            ['value' => 'waiting_for_buyer',  'label' => 'Waiting Buyer'],
            ['value' => 'waiting_for_seller', 'label' => 'Waiting Seller'],
            ['value' => 'resolved',           'label' => 'Resolved'],
            ['value' => 'all',                'label' => 'All'],
        ];
    }
}
