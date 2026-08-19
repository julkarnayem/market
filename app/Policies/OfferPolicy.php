<?php
namespace App\Policies;

use App\Enums\OfferStatus;
use App\Models\Offer;
use App\Models\User;

/**
 * Custom offers travel in both directions, so "who responds" is not "the
 * seller" any more — it is whoever did not create the offer. Payment is the one
 * thing that stays fixed to a role: only the buyer ever pays.
 */
class OfferPolicy
{
    /** Accept or decline: the party who did not send it. */
    public function respond(User $user, Offer $offer): bool
    {
        return $offer->isResponder($user->id) && $offer->isPending();
    }

    /** Withdraw: only the sender, and only while it is still pending. */
    public function cancel(User $user, Offer $offer): bool
    {
        return $offer->isCreator($user->id) && $offer->isPending();
    }

    /**
     * Pay: the buyer only, once accepted. A seller who accepted the buyer's
     * offer — or whose own offer was accepted — never pays for it.
     */
    public function pay(User $user, Offer $offer): bool
    {
        return $offer->isPayer($user->id) && $offer->status === OfferStatus::Accepted;
    }

    public function view(User $user, Offer $offer): bool
    {
        return $user->id === $offer->buyer_user_id || $user->id === $offer->seller_user_id;
    }
}
