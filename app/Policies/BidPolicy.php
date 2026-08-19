<?php
namespace App\Policies;

use App\Enums\BidStatus;
use App\Models\Asset;
use App\Models\Bid;
use App\Models\User;

/**
 * Who may do what with a bid.
 *
 * These checks mirror BidService's own guards on purpose. The service is the
 * last line — it re-reads the listing under a lock — but the policy keeps the
 * controller honest and gives the listing page an authoritative answer for
 * whether to render the New Bid button.
 */
class BidPolicy
{
    /** Anyone who can transact, except the seller, on a Single listing. */
    public function create(User $user, Asset $asset): bool
    {
        return $user->id !== $asset->user_id
            && $user->canTransact()
            && $asset->allowsBidding();
    }

    /** Only the listing owner, and only while the bid is still live. */
    public function accept(User $user, Bid $bid): bool
    {
        return (int) $bid->seller_user_id === $user->id
            && $bid->status === BidStatus::Active
            && $bid->asset !== null
            && !$bid->asset->hasAcceptedBid();
    }

    public function reject(User $user, Bid $bid): bool
    {
        return (int) $bid->seller_user_id === $user->id
            && in_array($bid->status, [BidStatus::Active, BidStatus::Outbid], true);
    }

    /** Only the bidder withdraws their own bid. */
    public function cancel(User $user, Bid $bid): bool
    {
        return (int) $bid->bidder_user_id === $user->id
            && in_array($bid->status, [BidStatus::Active, BidStatus::Outbid], true);
    }

    /** The winning bidder is the only one who may pay for an accepted bid. */
    public function pay(User $user, Bid $bid): bool
    {
        return (int) $bid->bidder_user_id === $user->id
            && $bid->status === BidStatus::Accepted;
    }

    public function view(User $user, Bid $bid): bool
    {
        return (int) $bid->bidder_user_id === $user->id
            || (int) $bid->seller_user_id === $user->id;
    }
}
