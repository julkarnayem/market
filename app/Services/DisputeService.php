<?php
namespace App\Services;

use App\Enums\DisputeMessageType;
use App\Enums\DisputeReason;
use App\Enums\DisputeResolutionType;
use App\Enums\DisputeStatus;
use App\Enums\OrderStatus;
use App\Enums\TransactionType;
use App\Models\Dispute;
use App\Models\DisputeEvidence;
use App\Models\DisputeMessage;
use App\Models\DisputeResolution;
use App\Models\Order;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Everything that happens to a dispute.
 *
 * Money only ever moves through executeOutcome(), which is the single place that
 * touches the wallet and the only place that stamps a terminal status. Both the
 * negotiated path (buyer and seller agree) and the admin path (an admin decides)
 * funnel through it, so there is one idempotency guard rather than one per entry
 * point.
 *
 * How an outcome maps onto a terminal status depends on who settled it:
 *
 *                    negotiated by the parties   decided by an admin
 *   full_refund      Refunded                    ResolvedBuyer
 *   partial_refund   Refunded                    ResolvedPartial
 *   replacement      Cancelled (no money moved)  Cancelled (no money moved)
 *   release_seller   — not negotiable —          ResolvedSeller
 *
 * The seller's earning is credited to their PENDING balance the moment the order
 * is paid (OrderService::markPaid), so a refund has to reverse that hold rather
 * than debit their available balance — see WalletService::debitPending.
 */
class DisputeService
{
    public function __construct(
        private readonly WalletService       $wallet,
        private readonly AuditLogger         $audit,
        private readonly NotificationService $notifications,
    ) {}

    // ─────────────────────────────────────────────────────────────────
    //  Opening
    // ─────────────────────────────────────────────────────────────────

    /**
     * The buyer opens a dispute against a paid, unsettled order.
     *
     * The order's own status becomes `disputed` and its `dispute_status` mirror
     * follows the dispute for the life of it, so order screens never have to
     * join. The pre-dispute status is preserved in the order's status history,
     * which is what cancel() restores from.
     */
    public function open(Order $order, User $buyer, DisputeReason $reason, string $description): Dispute
    {
        abort_unless((int) $order->buyer_user_id === $buyer->id, 403);
        abort_unless($order->status->canOpenDispute(), 422, 'A dispute cannot be opened for this order right now.');
        abort_if(
            $order->disputes()->whereIn('status', DisputeStatus::activeValues())->exists(),
            422,
            'This order already has an open dispute.',
        );

        return DB::transaction(function () use ($order, $buyer, $reason, $description) {
            $from = $order->status->value;

            $dispute = Dispute::create([
                'order_id'         => $order->id,
                'opened_by'        => $buyer->id,
                'reason_code'      => $reason,
                'description'      => $description,
                'status'           => DisputeStatus::Open,
                'last_activity_at' => now(),
            ]);

            // The handle is derived from the id, so it can only be set post-insert.
            $dispute->update(['reference' => 'D-' . (10000 + (int) $dispute->id)]);

            $order->update(['status' => OrderStatus::Disputed, 'dispute_status' => DisputeStatus::Open->value]);
            $order->statusHistory()->create([
                'from_status' => $from,
                'to_status'   => OrderStatus::Disputed->value,
                'changed_by'  => $buyer->id,
                'note'        => 'Dispute ' . $dispute->reference . ' opened: ' . $reason->label(),
                'created_at'  => now(),
            ]);

            $this->system($dispute, sprintf(
                '%s opened this dispute — %s.',
                $buyer->name,
                $reason->label(),
            ));

            $this->audit->log('dispute.opened', $dispute, [], [
                'order_id' => $order->id, 'reason_code' => $reason->value,
            ]);
            $this->notifyParty($dispute, $order->seller, 'dispute_opened', 'Dispute opened',
                "The buyer opened dispute {$dispute->reference} on order #{$order->order_number}. Please respond.");

            return $dispute;
        });
    }

    // ─────────────────────────────────────────────────────────────────
    //  The thread
    // ─────────────────────────────────────────────────────────────────

    /**
     * The thread as $viewer is allowed to see it.
     *
     * Internal notes are dropped for anyone who is not staff — this is the only
     * filter on them, so every payload that carries dispute messages must be
     * built from here rather than from the relation.
     *
     * @return list<array<string,mixed>>
     */
    public function threadFor(Dispute $dispute, User $viewer): array
    {
        $isAdmin = $viewer->isAdmin() || $viewer->hasPermission('disputes.manage');

        $messages = $dispute->messages()->with('author', 'evidence')->get()
            ->reject(fn (DisputeMessage $m) => $m->is_internal && !$isAdmin);

        return $messages->map(fn (DisputeMessage $m) => [
            'id'          => $m->id,
            'type'        => $m->type->value,
            'role'        => $m->role,
            'author'      => $m->author?->name ?? 'System',
            'body'        => $m->body,
            'is_internal' => (bool) $m->is_internal,
            'is_system'   => $m->isSystem(),
            'is_mine'     => (int) $m->user_id === $viewer->id,
            'at'          => $m->created_at?->format('d M Y, H:i'),
            'metadata'    => $m->metadata,
            'evidence'    => $m->evidence ? [
                'id'       => $m->evidence->id,
                'name'     => $m->evidence->file_original_name,
                'size'     => $m->evidence->sizeLabel(),
                'is_image' => $m->evidence->isImage(),
                'url'      => route('dashboard.disputes.evidence', [$dispute->id, $m->evidence->id]),
            ] : null,
        ])->values()->all();
    }

    /**
     * A participant (or an admin) adds a message.
     *
     * `client_message_id` is the dedupe key for a double-submitted composer: the
     * unique index on (dispute_id, client_message_id) makes the second insert a
     * no-op that returns the row already there.
     */
    public function postMessage(
        Dispute $dispute,
        User    $author,
        string  $body,
        ?string $clientMessageId = null,
        bool    $internal = false,
    ): DisputeMessage {
        $role = $dispute->roleOf($author);
        abort_if($role === null, 403, 'You are not a party to this dispute.');
        abort_if($internal && $role !== 'admin', 403);
        // Staff may annotate a closed dispute; the parties may not reopen the
        // conversation once it has been settled.
        abort_unless($dispute->isActive() || $role === 'admin', 422, 'This dispute is closed.');

        if ($clientMessageId !== null) {
            $existing = $dispute->messages()->where('client_message_id', $clientMessageId)->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        $message = $this->write($dispute, $internal ? DisputeMessageType::InternalNote : DisputeMessageType::Text, $body, [
            'user_id'           => $author->id,
            'role'              => $role,
            'is_internal'       => $internal,
            'client_message_id' => $clientMessageId,
        ]);

        // A seller's first reply is what moves the dispute off Open.
        if ($role === 'seller') {
            $this->markSellerResponded($dispute);
        }

        if (!$internal) {
            $this->notifyCounterparts($dispute, $author, 'dispute_message', 'New dispute message',
                "{$author->name} replied on dispute {$dispute->displayReference()}.");
        }

        return $message;
    }

    /**
     * Attach a file as evidence. The file lands on the `private` disk and is only
     * reachable through Dashboard\DisputeController::evidence(), which authorizes
     * the reader first.
     */
    public function attachEvidence(
        Dispute      $dispute,
        User         $author,
        UploadedFile $file,
        string       $note = '',
    ): DisputeEvidence {
        $role = $dispute->roleOf($author);
        abort_if($role === null, 403, 'You are not a party to this dispute.');
        abort_unless($dispute->isActive() || $role === 'admin', 422, 'This dispute is closed.');

        return DB::transaction(function () use ($dispute, $author, $file, $note, $role) {
            $path = $file->store("disputes/{$dispute->id}", 'private');

            $message = $this->write($dispute, DisputeMessageType::Evidence, $note !== '' ? $note : null, [
                'user_id' => $author->id,
                'role'    => $role,
            ]);

            $evidence = DisputeEvidence::create([
                'dispute_id'         => $dispute->id,
                'submitted_by'       => $author->id,
                'message_id'         => $message->id,
                'role'               => $role,
                'message'            => $note !== '' ? $note : null,
                'file_path'          => $path,
                'file_disk'          => 'private',
                'file_original_name' => $file->getClientOriginalName(),
                'file_mime'          => $file->getClientMimeType(),
                'file_size'          => $file->getSize(),
            ]);

            if ($role === 'seller') {
                $this->markSellerResponded($dispute);
            }

            $this->audit->log('dispute.evidence_added', $dispute, [], ['evidence_id' => $evidence->id]);
            $this->notifyCounterparts($dispute, $author, 'dispute_evidence', 'Dispute evidence added',
                "{$author->name} attached evidence to dispute {$dispute->displayReference()}.");

            return $evidence;
        });
    }

    /** An admin asks a named side for more evidence. Visible to both. */
    public function requestEvidence(Dispute $dispute, User $admin, string $from, string $note = ''): DisputeMessage
    {
        abort_unless(in_array($from, ['buyer', 'seller', 'both'], true), 422);
        abort_unless($dispute->isActive(), 422, 'This dispute is closed.');

        $who     = $from === 'both' ? 'both parties' : "the {$from}";
        $message = $this->system($dispute, trim("Admin requested further evidence from {$who}. {$note}"));

        $this->audit->log('dispute.evidence_requested', $dispute, [], ['from' => $from]);

        foreach ($this->partiesFor($dispute, $from) as $user) {
            $this->notifyParty($dispute, $user, 'dispute_evidence_requested', 'Evidence requested',
                "An admin asked for more evidence on dispute {$dispute->displayReference()}.");
        }

        return $message;
    }

    /** Staff-only commentary. Never leaves the admin screen. */
    public function addInternalNote(Dispute $dispute, User $admin, string $body): DisputeMessage
    {
        abort_unless($dispute->roleOf($admin) === 'admin', 403);

        $note = $this->write($dispute, DisputeMessageType::InternalNote, $body, [
            'user_id'     => $admin->id,
            'role'        => 'admin',
            'is_internal' => true,
        ]);

        $this->audit->log('dispute.internal_note', $dispute);

        return $note;
    }

    // ─────────────────────────────────────────────────────────────────
    //  Escalation
    // ─────────────────────────────────────────────────────────────────

    /**
     * Hand the dispute to an admin. Either party may escalate, and escalation is
     * sticky: once an admin owns it, a later message or proposal cannot quietly
     * put it back into Negotiating.
     */
    public function escalate(Dispute $dispute, User $actor, string $note = ''): void
    {
        $role = $dispute->roleOf($actor);
        abort_if($role === null, 403, 'You are not a party to this dispute.');
        abort_unless($dispute->isActive(), 422, 'This dispute is closed.');

        if ($dispute->status->isEscalated()) {
            return; // already an admin's problem
        }

        DB::transaction(function () use ($dispute, $actor, $note, $role) {
            $dispute->update([
                'status'           => DisputeStatus::Escalated,
                'escalated_at'     => now(),
                'last_activity_at' => now(),
            ]);
            $dispute->order?->update(['dispute_status' => DisputeStatus::Escalated->value]);

            $this->system($dispute, trim(sprintf(
                '%s escalated this dispute to an admin. %s',
                $role === 'admin' ? 'An admin' : ucfirst($role) . ' ' . $actor->name,
                $note,
            )));

            $this->audit->log('dispute.escalated', $dispute, [], ['by' => $role]);
        });

        $this->notifyCounterparts($dispute, $actor, 'dispute_escalated', 'Dispute escalated',
            "Dispute {$dispute->displayReference()} was escalated to an admin.");
    }

    // ─────────────────────────────────────────────────────────────────
    //  Negotiation
    // ─────────────────────────────────────────────────────────────────

    /**
     * Put an outcome on the table. Only one proposal is live at a time — a new
     * one supersedes whatever was pending, so neither side can stack offers and
     * then accept the stalest.
     */
    public function propose(
        Dispute               $dispute,
        User                  $actor,
        DisputeResolutionType $type,
        ?int                  $amountPoisha,
        string                $note = '',
    ): DisputeResolution {
        $role = $dispute->roleOf($actor);
        abort_if($role === null, 403, 'You are not a party to this dispute.');
        abort_unless($dispute->isActive(), 422, 'This dispute is closed.');
        abort_if(
            $type === DisputeResolutionType::ReleaseSeller,
            422,
            'Releasing the payment is an admin decision and cannot be proposed.',
        );

        if ($type->requiresAmount()) {
            abort_if($amountPoisha === null || $amountPoisha <= 0, 422, 'Name the refund amount.');
            abort_if(
                $amountPoisha > $dispute->maxRefundable(),
                422,
                'A refund cannot exceed the order total: ' . Money::format($dispute->maxRefundable()),
            );
        } else {
            $amountPoisha = null;
        }

        return DB::transaction(function () use ($dispute, $actor, $type, $amountPoisha, $note, $role) {
            $dispute->resolutions()
                ->where('status', DisputeResolution::STATUS_PROPOSED)
                ->update([
                    'status'       => DisputeResolution::STATUS_WITHDRAWN,
                    'responded_by' => $actor->id,
                    'responded_at' => now(),
                ]);

            $resolution = DisputeResolution::create([
                'dispute_id'  => $dispute->id,
                'proposed_by' => $actor->id,
                'role'        => $role,
                'type'        => $type,
                'amount'      => $amountPoisha,
                'note'        => $note !== '' ? $note : null,
                'status'      => DisputeResolution::STATUS_PROPOSED,
            ]);

            // Escalation outranks negotiation: an admin already owns this one.
            if (!$dispute->status->isEscalated()) {
                $dispute->update(['status' => DisputeStatus::Negotiating]);
                $dispute->order?->update(['dispute_status' => DisputeStatus::Negotiating->value]);
            }
            $this->touch($dispute);

            $this->system($dispute, sprintf(
                '%s proposed %s%s.',
                ucfirst($role) . ' ' . $actor->name,
                $type->label(),
                $amountPoisha !== null ? ' of ' . Money::format($amountPoisha) : '',
            ), ['resolution_id' => $resolution->id]);

            $this->audit->log('dispute.resolution_proposed', $dispute, [], [
                'type' => $type->value, 'amount' => $amountPoisha, 'by' => $role,
            ]);

            $this->notifyCounterparts($dispute, $actor, 'dispute_proposal', 'Settlement proposed',
                "{$actor->name} proposed {$type->label()} on dispute {$dispute->displayReference()}.");

            return $resolution;
        });
    }

    /** The proposer takes their own offer back. */
    public function withdrawProposal(DisputeResolution $resolution, User $actor): void
    {
        abort_unless((int) $resolution->proposed_by === $actor->id, 403);
        abort_unless($resolution->isPending(), 422, 'That proposal is no longer on the table.');

        $resolution->update([
            'status'       => DisputeResolution::STATUS_WITHDRAWN,
            'responded_by' => $actor->id,
            'responded_at' => now(),
        ]);

        $dispute = $resolution->dispute;
        $this->system($dispute, "{$actor->name} withdrew their proposal.");
        $this->touch($dispute);
        $this->audit->log('dispute.resolution_withdrawn', $dispute, [], ['resolution_id' => $resolution->id]);
    }

    /** The other side says no. The dispute stays open for another round. */
    public function declineProposal(DisputeResolution $resolution, User $actor, string $note = ''): void
    {
        $dispute = $resolution->dispute;
        $role    = $dispute->roleOf($actor);

        abort_if($role === null, 403, 'You are not a party to this dispute.');
        abort_if((int) $resolution->proposed_by === $actor->id, 403, 'You cannot answer your own proposal.');
        abort_unless($resolution->isPending(), 422, 'That proposal is no longer on the table.');
        abort_unless($role === $resolution->awaitingRole() || $role === 'admin', 403);

        $resolution->update([
            'status'       => DisputeResolution::STATUS_DECLINED,
            'responded_by' => $actor->id,
            'responded_at' => now(),
        ]);

        $this->system($dispute, trim("{$actor->name} declined the proposal. {$note}"));
        $this->touch($dispute);
        $this->audit->log('dispute.resolution_declined', $dispute, [], ['resolution_id' => $resolution->id]);

        $this->notifyCounterparts($dispute, $actor, 'dispute_proposal_declined', 'Proposal declined',
            "Your proposal on dispute {$dispute->displayReference()} was declined.");
    }

    /**
     * The other side agrees — this is the negotiated settlement, and it moves the
     * money straight away. A replayed accept finds executed_at already stamped
     * and moves nothing a second time.
     */
    public function acceptProposal(DisputeResolution $resolution, User $actor): void
    {
        $dispute = $resolution->dispute;
        $role    = $dispute->roleOf($actor);

        abort_if($role === null, 403, 'You are not a party to this dispute.');
        abort_if((int) $resolution->proposed_by === $actor->id, 403, 'You cannot accept your own proposal.');
        abort_unless($resolution->isPending(), 422, 'That proposal is no longer on the table.');
        abort_unless($role === $resolution->awaitingRole(), 403, 'This proposal is not yours to accept.');

        $moved = $resolution->type !== DisputeResolutionType::Replacement;

        // Only a partial refund carries a figure — propose() nulls the amount on
        // every other type — so a full refund's amount is derived here, from the
        // order, exactly as resolveFullRefund() derives it. Reading it off the
        // proposal instead would hand moveMoney() a zero and abort the accept.
        $amount = $resolution->type === DisputeResolutionType::FullRefund
            ? $dispute->maxRefundable()
            : (int) ($resolution->amount ?? 0);

        $this->executeOutcome(
            dispute:    $dispute,
            actor:      $actor,
            type:       $resolution->type,
            amount:     $amount,
            final:      $moved ? DisputeStatus::Refunded : DisputeStatus::Cancelled,
            note:       (string) ($resolution->note ?? ''),
            resolution: $resolution,
            settledBy:  'agreement',
        );
    }

    // ─────────────────────────────────────────────────────────────────
    //  Admin decisions
    // ─────────────────────────────────────────────────────────────────

    /** FULL REFUND — the buyer gets everything back; the seller's hold is reversed. */
    public function resolveFullRefund(Dispute $dispute, User $admin, string $note = ''): void
    {
        $this->decide($dispute, $admin, DisputeResolutionType::FullRefund,
            $dispute->maxRefundable(), DisputeStatus::ResolvedBuyer, $note);
    }

    /** PARTIAL REFUND — the buyer gets part of it back; the seller keeps the rest. */
    public function resolvePartialRefund(Dispute $dispute, User $admin, int $refundPoisha, string $note = ''): void
    {
        abort_if($refundPoisha <= 0, 422, 'Refund amount must be positive.');
        abort_if(
            $refundPoisha > $dispute->maxRefundable(),
            422,
            'Refund cannot exceed order total: ' . Money::format($dispute->maxRefundable()),
        );

        $this->decide($dispute, $admin, DisputeResolutionType::PartialRefund,
            $refundPoisha, DisputeStatus::ResolvedPartial, $note);
    }

    /** RELEASE TO SELLER — the claim failed; the order completes as normal. */
    public function resolveSellerRelease(Dispute $dispute, User $admin, string $note = ''): void
    {
        $this->decide($dispute, $admin, DisputeResolutionType::ReleaseSeller,
            0, DisputeStatus::ResolvedSeller, $note);
    }

    /**
     * REPLACEMENT — no money moves. The order goes back to awaiting delivery so
     * the seller can re-deliver, and the buyer-protection window restarts from
     * that new delivery.
     */
    public function resolveReplacement(Dispute $dispute, User $admin, string $note = ''): void
    {
        $this->decide($dispute, $admin, DisputeResolutionType::Replacement,
            0, DisputeStatus::Cancelled, $note);
    }

    /**
     * Close a dispute with no financial outcome — the buyer withdrawing their own
     * claim, or an admin closing a dead one. The order returns to the status it
     * held before the dispute, so a later dispute on the same order is possible.
     */
    public function cancel(Dispute $dispute, User $actor, string $note = ''): void
    {
        $role = $dispute->roleOf($actor);
        abort_if($role === null, 403, 'You are not a party to this dispute.');
        abort_if($role === 'seller', 403, 'A seller cannot close a dispute against them.');
        abort_unless($dispute->isActive(), 422, 'This dispute is already closed.');

        DB::transaction(function () use ($dispute, $actor, $note, $role) {
            $locked = Dispute::whereKey($dispute->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($locked->isActive(), 422, 'This dispute is already closed.');

            $locked->update([
                'status'           => DisputeStatus::Cancelled,
                'resolution'       => 'cancelled',
                'resolution_note'  => $note !== '' ? $note : null,
                'resolved_by'      => $actor->id,
                'resolved_at'      => now(),
                'last_activity_at' => now(),
            ]);

            $this->restoreOrder($locked, DisputeStatus::Cancelled, $actor);

            $this->write($dispute, DisputeMessageType::System, trim(sprintf(
                '%s closed this dispute with no refund. %s',
                $role === 'admin' ? 'An admin' : $actor->name,
                $note,
            )));

            $this->audit->log('dispute.cancelled', $dispute, [], ['by' => $role]);
        });

        $this->notifyCounterparts($dispute, $actor, 'dispute_closed', 'Dispute closed',
            "Dispute {$dispute->displayReference()} was closed with no refund.");
    }

    // ─────────────────────────────────────────────────────────────────
    //  Execution
    // ─────────────────────────────────────────────────────────────────

    /**
     * An admin's decision. Recorded in dispute_resolutions pre-accepted, so the
     * audit trail and the negotiation history are one history.
     */
    private function decide(
        Dispute               $dispute,
        User                  $admin,
        DisputeResolutionType $type,
        int                   $amount,
        DisputeStatus         $final,
        string                $note,
    ): void {
        abort_unless($dispute->roleOf($admin) === 'admin', 403);
        abort_unless($dispute->isResolvable(), 422, 'Dispute cannot be resolved in its current state.');

        $resolution = DB::transaction(fn () => DisputeResolution::create([
            'dispute_id'   => $dispute->id,
            'proposed_by'  => $admin->id,
            'role'         => 'admin',
            'type'         => $type,
            'amount'       => $this->refundAmountFor($type, $amount),
            'note'         => $note !== '' ? $note : null,
            'status'       => DisputeResolution::STATUS_ACCEPTED,
            'responded_by' => $admin->id,
            'responded_at' => now(),
        ]));

        $this->executeOutcome(
            dispute:    $dispute,
            actor:      $admin,
            type:       $type,
            amount:     $amount,
            final:      $final,
            note:       $note,
            resolution: $resolution,
            settledBy:  'admin',
        );
    }

    /**
     * The only place money moves and the only place a terminal status is set.
     *
     * Everything runs in one transaction: the dispute row is locked and
     * re-checked inside it, and `executed_at` on the resolution is stamped
     * alongside the wallet writes. A replay therefore either finds the dispute
     * already settled or the resolution already executed, and does nothing.
     */
    private function executeOutcome(
        Dispute               $dispute,
        User                  $actor,
        DisputeResolutionType $type,
        int                   $amount,
        DisputeStatus         $final,
        string                $note,
        DisputeResolution     $resolution,
        string                $settledBy,
    ): void {
        abort_unless($dispute->isResolvable(), 422, 'Dispute cannot be resolved in its current state.');

        DB::transaction(function () use ($dispute, $actor, $type, $amount, $final, $note, $resolution, $settledBy) {
            $locked = Dispute::whereKey($dispute->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($locked->isResolvable(), 422, 'This dispute has already been resolved.');

            $pinned = DisputeResolution::whereKey($resolution->getKey())->lockForUpdate()->firstOrFail();
            if ($pinned->executed_at !== null) {
                return; // replay — the money already moved
            }

            $order = Order::whereKey($locked->order_id)->lockForUpdate()->firstOrFail();

            $this->moveMoney($order, $type, $amount);

            // Only the two refund outcomes have a figure worth recording; a
            // replacement and a release move nothing the buyer gets back.
            $refunded = $this->refundAmountFor($type, $amount);

            $locked->update([
                'status'            => $final,
                'resolution'        => $type->value,
                'resolution_type'   => $type->value,
                'resolution_amount' => $refunded,
                'resolution_note'   => $note !== '' ? $note : null,
                'resolved_by'       => $actor->id,
                'resolved_at'       => now(),
                'last_activity_at'  => now(),
            ]);

            $pinned->update([
                'status'       => DisputeResolution::STATUS_ACCEPTED,
                'responded_by' => $actor->id,
                'responded_at' => $pinned->responded_at ?? now(),
                'executed_at'  => now(),
            ]);

            $order->update(['dispute_status' => $final->value]);

            $this->write($dispute, DisputeMessageType::AdminDecision, trim(sprintf(
                '%s: %s%s. %s',
                $settledBy === 'admin' ? 'Admin decision' : 'Settled by agreement',
                $type->label(),
                $refunded !== null ? ' of ' . Money::format($refunded) : '',
                $note,
            )), [
                'user_id' => $actor->id,
                'role'    => $settledBy === 'admin' ? 'admin' : $locked->roleOf($actor),
            ], ['resolution_id' => $pinned->id, 'type' => $type->value, 'amount' => $refunded]);

            $this->audit->log("dispute.{$type->value}", $locked, [], [
                'amount' => $amount, 'settled_by' => $settledBy, 'status' => $final->value,
            ]);
        });

        $this->notifyParty($dispute, $dispute->order?->buyer, 'dispute_resolved', 'Dispute resolved',
            "Dispute {$dispute->displayReference()} was resolved: {$type->label()}.");
        $this->notifyParty($dispute, $dispute->order?->seller, 'dispute_resolved', 'Dispute resolved',
            "Dispute {$dispute->displayReference()} was resolved: {$type->label()}.");
    }

    /**
     * The wallet and order side of an outcome. Called inside executeOutcome's
     * transaction with the order already locked.
     */
    private function moveMoney(Order $order, DisputeResolutionType $type, int $amount): void
    {
        $earning = (int) $order->seller_earning;

        switch ($type) {
            case DisputeResolutionType::FullRefund:
                $this->wallet->creditAvailable($order->buyer, $amount, TransactionType::Refund, $order,
                    "Full refund for order #{$order->order_number}");
                // The earning was credited to pending at payment time, so the
                // seller's side of a refund is a reversal of that hold — not a
                // debit of their available balance, which they may have spent.
                if ($earning > 0) {
                    $this->wallet->debitPending($order->seller, $earning, $order,
                        "Earning reversed — full refund on order #{$order->order_number}");
                }
                $order->update([
                    'status'           => OrderStatus::Refunded,
                    'earning_released' => false,
                ]);
                break;

            case DisputeResolutionType::PartialRefund:
                $this->wallet->creditAvailable($order->buyer, $amount, TransactionType::PartialRefund, $order,
                    "Partial refund for order #{$order->order_number}");

                // The seller loses the same proportion of their earning that the
                // buyer got back, and the remainder is released to them — without
                // this the balance of the hold would sit in pending forever.
                $buyerTotal = max(1, (int) $order->buyer_total);
                $reversed   = (int) round($earning * min($amount, $buyerTotal) / $buyerTotal);
                $released   = max(0, $earning - $reversed);

                if ($reversed > 0) {
                    $this->wallet->debitPending($order->seller, $reversed, $order,
                        "Earning reduced — partial refund on order #{$order->order_number}");
                }
                if ($released > 0) {
                    $this->wallet->releasePending($order->seller, $released, $order,
                        "Earning released after partial refund on order #{$order->order_number}");
                }

                $order->update([
                    'status'                      => OrderStatus::PartiallyRefunded,
                    'earning_released'            => true,
                    'completed_at'                => now(),
                    'seller_earning_available_at' => now(),
                ]);
                break;

            case DisputeResolutionType::ReleaseSeller:
                abort_if($order->earning_released, 422, 'Seller earning was already released.');
                if ($earning > 0) {
                    $this->wallet->releasePending($order->seller, $earning, $order,
                        "Dispute resolved — earning released for order #{$order->order_number}");
                }
                $order->update([
                    'status'                      => OrderStatus::Completed,
                    'earning_released'            => true,
                    'completed_at'                => now(),
                    'seller_earning_available_at' => now(),
                ]);
                break;

            case DisputeResolutionType::Replacement:
                // No money moves. The seller owes a re-delivery, so the order
                // goes back to awaiting one and the protection window restarts
                // when they deliver again.
                $order->update([
                    'status'          => OrderStatus::DeliveryPending,
                    'delivery_status' => 'not_started',
                    'delivered_at'    => null,
                    'auto_complete_at'=> null,
                ]);
                break;
        }
    }

    /**
     * Put the order back where it was before the dispute. Reads the status the
     * dispute displaced out of the order's own history rather than assuming
     * Delivered — a dispute can be opened from Paid or Delivery Pending too.
     */
    private function restoreOrder(Dispute $dispute, DisputeStatus $final, User $actor): void
    {
        $order = $dispute->order;
        if ($order === null) {
            return;
        }

        $previous = $order->statusHistory()
            ->where('to_status', OrderStatus::Disputed->value)
            ->latest('id')
            ->value('from_status');

        $restored = OrderStatus::tryFrom((string) $previous) ?? OrderStatus::Delivered;

        $order->update(['status' => $restored, 'dispute_status' => $final->value]);
        $order->statusHistory()->create([
            'from_status' => OrderStatus::Disputed->value,
            'to_status'   => $restored->value,
            'changed_by'  => $actor->id,
            'note'        => 'Dispute ' . $dispute->displayReference() . ' closed with no refund',
            'created_at'  => now(),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    //  Internals
    // ─────────────────────────────────────────────────────────────────

    /**
     * What the buyer gets back under this outcome, or null when the outcome is
     * not a refund at all. A full refund's figure is the whole buyer_total, so it
     * is recorded even though the type carries no caller-supplied amount.
     */
    private function refundAmountFor(DisputeResolutionType $type, int $amount): ?int
    {
        return match ($type) {
            DisputeResolutionType::FullRefund,
            DisputeResolutionType::PartialRefund => $amount,
            default                              => null,
        };
    }

    /** Open → SellerResponded on the seller's first contribution. */
    private function markSellerResponded(Dispute $dispute): void
    {
        if ($dispute->status !== DisputeStatus::Open) {
            return; // Negotiating and Escalated both outrank this
        }

        $dispute->update([
            'status'              => DisputeStatus::SellerResponded,
            'seller_responded_at' => $dispute->seller_responded_at ?? now(),
        ]);
        $dispute->order?->update(['dispute_status' => DisputeStatus::SellerResponded->value]);
    }

    /** A system event: no author, visible to everyone. */
    private function system(Dispute $dispute, string $body, array $metadata = []): DisputeMessage
    {
        return $this->write($dispute, DisputeMessageType::System, $body, [], $metadata);
    }

    /**
     * Write a thread row and bump the dispute's activity clock — the admin queue
     * orders on it, so no write may skip it.
     */
    private function write(
        Dispute            $dispute,
        DisputeMessageType $type,
        ?string            $body,
        array              $attributes = [],
        array              $metadata = [],
    ): DisputeMessage {
        $message = $dispute->messages()->create($attributes + [
            'type'        => $type,
            'body'        => $body,
            'metadata'    => $metadata ?: null,
            'is_internal' => $type === DisputeMessageType::InternalNote,
        ]);

        $this->touch($dispute);

        return $message;
    }

    private function touch(Dispute $dispute): void
    {
        $dispute->update(['last_activity_at' => now()]);
    }

    /** @return list<User> */
    private function partiesFor(Dispute $dispute, string $from): array
    {
        $order = $dispute->order;
        if ($order === null) {
            return [];
        }

        return match ($from) {
            'buyer'  => array_filter([$order->buyer]),
            'seller' => array_filter([$order->seller]),
            default  => array_filter([$order->buyer, $order->seller]),
        };
    }

    /** Everyone on the dispute except whoever just acted. */
    private function notifyCounterparts(Dispute $dispute, User $actor, string $type, string $title, string $message): void
    {
        foreach ($this->partiesFor($dispute, 'both') as $user) {
            if ($user->id !== $actor->id) {
                $this->notifyParty($dispute, $user, $type, $title, $message);
            }
        }
    }

    private function notifyParty(Dispute $dispute, ?User $user, string $type, string $title, string $message): void
    {
        if ($user === null) {
            return;
        }

        $this->notifications->inApp($user, $type, $title, $message, [
            'dispute_id' => $dispute->id,
            'url'        => route('dashboard.disputes.show', $dispute->id),
        ]);
    }
}
