<?php
namespace App\Http\Controllers\Dashboard;

use App\Enums\DisputeResolutionType;
use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\DisputeEvidence;
use App\Models\DisputeResolution;
use App\Services\DisputeService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

/**
 * The buyer's and seller's side of a dispute: the thread, evidence, and the
 * settlement they can reach without an admin.
 *
 * Every action authorizes against DisputePolicy first and the service checks
 * again under a lock. Nothing here trusts an id in the request body — the
 * dispute comes from the route, its order and parties are derived from it, and
 * the caller's role is whatever Dispute::roleOf() says it is.
 */
class DisputeController extends Controller
{
    public function __construct(private readonly DisputeService $disputes) {}

    /** GET /dashboard/disputes — every dispute this user is a party to. */
    public function index()
    {
        $userId = Auth::id();

        $disputes = Dispute::query()
            ->whereHas('order', fn ($q) => $q
                ->where('buyer_user_id', $userId)
                ->orWhere('seller_user_id', $userId))
            ->with('order.asset', 'order.buyer', 'order.seller')
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Dashboard/Disputes/Index', [
            'disputes' => $disputes->through(fn (Dispute $d) => [
                'id'           => $d->id,
                'reference'    => $d->displayReference(),
                'order_number' => $d->order?->order_number ?? '—',
                'asset_title'  => $d->order?->asset?->title ?? '—',
                'reason'       => $d->reason_code?->label() ?? '—',
                'status'       => $d->status->value,
                'status_label' => $d->status->label(),
                'is_active'    => $d->isActive(),
                'role'         => $d->roleOf(Auth::user()),
                'counterparty' => (int) $d->order?->buyer_user_id === Auth::id()
                    ? ($d->order?->seller?->name ?? '—')
                    : ($d->order?->buyer?->name ?? '—'),
                'total'        => Money::format($d->order?->buyer_total ?? 0),
                'activity'     => $d->last_activity_at?->diffForHumans() ?? '—',
                'url'          => route('dashboard.disputes.show', $d->viewRouteParams()),
            ]),
        ]);
    }

    /** GET /dashboard/disputes/{orderNumber}/{reference} — the thread. */
    public function show(string $orderNumber, Dispute $dispute)
    {
        $this->authorize('view', $dispute);

        // The reference already identified the row; the order number in front of it
        // is part of the address, so a mismatched one is a wrong URL rather than a
        // detail to quietly ignore.
        abort_unless($orderNumber === $dispute->viewRouteParams()['orderNumber'], 404);

        $user  = Auth::user();
        $role  = $dispute->roleOf($user);
        $order = $dispute->order;
        $dispute->load('order.asset', 'order.buyer', 'order.seller', 'opener', 'resolver');

        $pending = $dispute->pendingResolution();

        return Inertia::render('Dashboard/Disputes/Show', [
            'dispute' => [
                'id'                => $dispute->id,
                'reference'         => $dispute->displayReference(),
                'status'            => $dispute->status->value,
                'status_label'      => $dispute->status->label(),
                'is_active'         => $dispute->isActive(),
                'is_escalated'      => $dispute->status->isEscalated(),
                'reason'            => $dispute->reason_code?->label() ?? '—',
                'description'       => $dispute->description,
                'opener'            => $dispute->opener?->name ?? '—',
                'opened'            => $dispute->created_at?->format('d M Y, H:i'),
                'resolution_type'   => $dispute->resolution_type
                    ? ucwords(str_replace('_', ' ', $dispute->resolution_type)) : null,
                'resolution_amount' => $dispute->resolution_amount
                    ? Money::format($dispute->resolution_amount) : null,
                'resolution_note'   => $dispute->resolution_note,
                'resolved_at'       => $dispute->resolved_at?->format('d M Y, H:i'),
            ],
            'order' => [
                'number'          => $order?->order_number ?? '—',
                'url'             => $order ? route('dashboard.orders.show', $order->id) : null,
                'asset_title'     => $order?->asset?->title ?? '—',
                'buyer'           => $order?->buyer?->name ?? '—',
                'seller'          => $order?->seller?->name ?? '—',
                'buyer_total'     => Money::format($order?->buyer_total ?? 0),
                // Raw taka drives the partial-refund input's max; the server
                // still owns the ceiling (DisputeService::propose re-checks it).
                'buyer_total_bdt' => Money::toBdt($order?->buyer_total ?? 0),
            ],
            // Built by the service so internal notes are filtered in one place.
            'thread'  => $this->disputes->threadFor($dispute, $user),
            'role'    => $role,
            'pending' => $pending ? [
                'id'         => $pending->id,
                'type'       => $pending->type->value,
                'type_label' => $pending->type->label(),
                'amount'     => $pending->amount ? Money::format($pending->amount) : null,
                'note'       => $pending->note,
                'proposer'   => $pending->proposer?->name ?? '—',
                'role'       => $pending->role,
                'is_mine'    => (int) $pending->proposed_by === $user->id,
                'awaiting'   => $pending->awaitingRole(),
                'at'         => $pending->created_at?->format('d M Y, H:i'),
            ] : null,
            'history' => $dispute->resolutions()->with('proposer')->get()
                ->map(fn (DisputeResolution $r) => [
                    'id'         => $r->id,
                    'type_label' => $r->type->label(),
                    'amount'     => $r->amount ? Money::format($r->amount) : null,
                    'role'       => $r->role,
                    'proposer'   => $r->proposer?->name ?? '—',
                    'status'     => $r->status,
                    'executed'   => $r->isExecuted(),
                    'at'         => $r->created_at?->format('d M, H:i'),
                ])->all(),
            'options' => DisputeResolutionType::negotiableOptions(),
            // Same contract as the order chat (Dashboard\MessageController): when
            // no broadcast driver is configured the page polls for new thread rows
            // instead. Wiring a driver turns the polling off without a code change.
            'isRealtimeReady' => !in_array((string) config('broadcasting.default'), ['', 'null'], true),
            'can'     => [
                'message'  => $user->can('message', $dispute),
                'evidence' => $user->can('addEvidence', $dispute),
                'propose'  => $user->can('propose', $dispute),
                'escalate' => $user->can('escalate', $dispute),
                'cancel'   => $user->can('cancel', $dispute),
                'respond'  => $pending !== null && $user->can('respondToProposal', $pending),
                'withdraw' => $pending !== null && $user->can('withdrawProposal', $pending),
            ],
        ]);
    }

    /** POST /dashboard/disputes/{dispute}/messages */
    public function message(Request $request, Dispute $dispute)
    {
        $this->authorize('message', $dispute);

        $data = $request->validate([
            'body'              => 'required|string|min:2|max:5000',
            'client_message_id' => 'nullable|string|max:64',
        ]);

        $this->disputes->postMessage(
            $dispute,
            Auth::user(),
            $data['body'],
            $data['client_message_id'] ?? null,
        );

        return back()->with('success', 'Message sent.');
    }

    /** POST /dashboard/disputes/{dispute}/evidence */
    public function storeEvidence(Request $request, Dispute $dispute)
    {
        $this->authorize('addEvidence', $dispute);

        $data = $request->validate([
            // 10 MB, and only the formats an admin can actually open.
            'file' => 'required|file|max:10240|mimes:jpg,jpeg,png,webp,gif,pdf,txt,zip',
            'note' => 'nullable|string|max:1000',
        ]);

        $this->disputes->attachEvidence(
            $dispute,
            Auth::user(),
            $request->file('file'),
            $data['note'] ?? '',
        );

        return back()->with('success', 'Evidence attached.');
    }

    /**
     * GET /dashboard/disputes/{dispute}/evidence/{evidence}
     *
     * The only route to an evidence file. Files sit on the `private` disk with no
     * public URL, so this is where the reader is authorized — and the evidence
     * must belong to the dispute in the path, or a party to one dispute could
     * walk another's ids.
     */
    public function evidence(Dispute $dispute, DisputeEvidence $evidence)
    {
        $this->authorize('view', $dispute);
        abort_unless((int) $evidence->dispute_id === (int) $dispute->id, 404);
        abort_unless($evidence->hasFile(), 404);

        $disk = Storage::disk($evidence->file_disk ?: 'private');
        abort_unless($disk->exists($evidence->file_path), 404);

        return $disk->download(
            $evidence->file_path,
            $evidence->file_original_name ?: basename($evidence->file_path),
        );
    }

    /** POST /dashboard/disputes/{dispute}/escalate */
    public function escalate(Request $request, Dispute $dispute)
    {
        $this->authorize('escalate', $dispute);

        $data = $request->validate(['note' => 'nullable|string|max:1000']);
        $this->disputes->escalate($dispute, Auth::user(), $data['note'] ?? '');

        return back()->with('success', 'Escalated. An admin will review this dispute.');
    }

    /** POST /dashboard/disputes/{dispute}/proposals */
    public function propose(Request $request, Dispute $dispute)
    {
        $this->authorize('propose', $dispute);

        $data = $request->validate([
            'type'       => ['required', DisputeResolutionType::negotiableRule()],
            'amount_bdt' => 'nullable|numeric|min:0.01|max:99999999',
            'note'       => 'nullable|string|max:1000',
        ]);

        $type = DisputeResolutionType::from($data['type']);

        $this->disputes->propose(
            $dispute,
            Auth::user(),
            $type,
            // Only a partial refund carries a figure; the service rejects a
            // missing one rather than silently proposing zero.
            $type->requiresAmount() && isset($data['amount_bdt'])
                ? Money::toPoisha($data['amount_bdt'])
                : null,
            $data['note'] ?? '',
        );

        return back()->with('success', 'Proposal sent to the other party.');
    }

    /** POST /dashboard/dispute-proposals/{resolution}/accept */
    public function acceptProposal(DisputeResolution $resolution)
    {
        $this->authorize('respondToProposal', $resolution);

        $this->disputes->acceptProposal($resolution, Auth::user());

        return back()->with('success', 'Proposal accepted — the dispute is settled.');
    }

    /** POST /dashboard/dispute-proposals/{resolution}/decline */
    public function declineProposal(Request $request, DisputeResolution $resolution)
    {
        $this->authorize('respondToProposal', $resolution);

        $data = $request->validate(['note' => 'nullable|string|max:1000']);
        $this->disputes->declineProposal($resolution, Auth::user(), $data['note'] ?? '');

        return back()->with('success', 'Proposal declined.');
    }

    /** POST /dashboard/dispute-proposals/{resolution}/withdraw */
    public function withdrawProposal(DisputeResolution $resolution)
    {
        $this->authorize('withdrawProposal', $resolution);

        $this->disputes->withdrawProposal($resolution, Auth::user());

        return back()->with('success', 'Proposal withdrawn.');
    }

    /** POST /dashboard/disputes/{dispute}/cancel — the buyer drops their claim. */
    public function cancel(Request $request, Dispute $dispute)
    {
        $this->authorize('cancel', $dispute);

        $data = $request->validate(['note' => 'nullable|string|max:1000']);
        $this->disputes->cancel($dispute, Auth::user(), $data['note'] ?? '');

        return redirect()->route('dashboard.disputes.show', $dispute->viewRouteParams())
            ->with('success', 'Dispute closed.');
    }
}
