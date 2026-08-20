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
     * Parties may write while the dispute is live; staff may annotate one that
     * has already been settled.
     */
    public function message(User $user, Dispute $dispute): bool
    {
        $role = $dispute->roleOf($user);

        return $role === 'admin' || ($role !== null && $dispute->isActive());
    }

    /** Evidence follows the same rule as a message — it *is* a message. */
    public function addEvidence(User $user, Dispute $dispute): bool
    {
        return $this->message($user, $dispute);
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
     * The buyer can drop the claim they raised; an admin can close a dead one.
     * A seller cannot close a dispute filed against them.
     */
    public function cancel(User $user, Dispute $dispute): bool
    {
        $role = $dispute->roleOf($user);

        return $dispute->isActive() && in_array($role, ['buyer', 'admin'], true);
    }
}
