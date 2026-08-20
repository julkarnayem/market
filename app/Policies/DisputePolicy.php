<?php
namespace App\Policies;

use App\Models\Dispute;
use App\Models\DisputeResolution;
use App\Models\User;

/**
 * Who may do what with a dispute.
 *
 * Every ability here answers for a *party* — buyer, seller, or an admin acting
 * on the thread. The money decisions are not here: those are gated on the
 * `disputes.manage` permission in Admin\DisputeController, because they are a
 * staff capability rather than a relationship to one dispute.
 *
 * These checks mirror DisputeService's own guards. The service is the last line
 * — it re-reads and locks the row — but the policy keeps the controllers honest
 * and gives the Vue pages an authoritative answer for which buttons to render.
 */
class DisputePolicy
{
    /** Buyer, seller and staff. Nobody else, ever — threads are private. */
    public function view(User $user, Dispute $dispute): bool
    {
        return $dispute->roleOf($user) !== null;
    }

    /**
     * Party speech. Staff are deliberately excluded: an admin communicates through
     * DisputeService::announce() or an internal note, never as a peer in the
     * buyer↔seller thread — postMessage() enforces the same rule.
     */
    public function message(User $user, Dispute $dispute): bool
    {
        return $dispute->isParty($user) && $dispute->isActive();
    }

    /**
     * Evidence is a record, not speech, so it follows attachEvidence()'s rule
     * rather than message()'s: staff may attach to a dispute they are reviewing,
     * including one already settled.
     */
    public function addEvidence(User $user, Dispute $dispute): bool
    {
        if ($dispute->isStaff($user)) {
            return true;
        }

        return $dispute->isParty($user) && $dispute->isActive();
    }

    /** Either party, once, while the dispute is still live. */
    public function escalate(User $user, Dispute $dispute): bool
    {
        return $dispute->isParty($user)
            && $dispute->isActive()
            && !$dispute->status->isEscalated();
    }

    /**
     * Only buyer and seller negotiate. An admin does not "propose" — they decide,
     * which is a different route and a different permission.
     */
    public function propose(User $user, Dispute $dispute): bool
    {
        return $dispute->isParty($user) && $dispute->isActive();
    }

    /** Accept or decline: the other side's call, never the proposer's own. */
    public function respondToProposal(User $user, DisputeResolution $resolution): bool
    {
        $dispute = $resolution->dispute;

        return $dispute !== null
            && $resolution->isPending()
            && $dispute->isActive()
            && (int) $resolution->proposed_by !== $user->id
            && $dispute->roleOf($user) === $resolution->awaitingRole();
    }

    /** The proposer takes back their own live offer. */
    public function withdrawProposal(User $user, DisputeResolution $resolution): bool
    {
        return $resolution->isPending() && (int) $resolution->proposed_by === $user->id;
    }

    /**
     * The buyer can drop the claim they raised; staff can close a dead one.
     * A seller cannot close a dispute filed against them.
     */
    public function cancel(User $user, Dispute $dispute): bool
    {
        if (!$dispute->isActive()) {
            return false;
        }

        return $dispute->isStaff($user) || $dispute->roleOf($user) === 'buyer';
    }
}
