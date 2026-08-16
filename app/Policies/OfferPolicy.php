<?php
namespace App\Policies;

use App\Models\Offer;
use App\Models\User;

class OfferPolicy
{
    public function respond(User $user, Offer $offer): bool
    {
        return $user->id === $offer->seller_user_id;
    }
    public function view(User $user, Offer $offer): bool
    {
        return $user->id === $offer->buyer_user_id || $user->id === $offer->seller_user_id;
    }
}
