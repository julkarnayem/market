<?php
namespace App\Services;

use App\Enums\OfferStatus;
use App\Models\Asset;
use App\Models\Offer;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class OfferService
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * Create a new offer. Enforces all business rules server-side.
     */
    public function create(User $buyer, Asset $asset, int $amountPoisha, int $quantity = 1, ?string $message = null): Offer
    {
        // Authorization guards
        abort_if($buyer->id === $asset->user_id, 403, 'You cannot make an offer on your own listing.');
        abort_unless($buyer->canTransact(), 403, 'Your account is not in good standing.');
        abort_unless($asset->status->value === 'published', 422, 'This listing is not available.');
        abort_if($asset->isSoldOut(), 422, 'This listing is sold out.');
        abort_if($amountPoisha <= 0, 422, 'Offer amount must be greater than zero.');

        // One active pending offer per buyer per listing
        $existing = Offer::where('asset_id', $asset->id)
            ->where('buyer_user_id', $buyer->id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();

        if ($existing) {
            abort(422, 'You already have an active offer on this listing. Wait for it to expire or be responded to.');
        }

        $validityHours = $this->settings->offerValidityHours();

        return DB::transaction(function () use ($buyer, $asset, $amountPoisha, $quantity, $message, $validityHours) {
            return Offer::create([
                'asset_id'       => $asset->id,
                'buyer_user_id'  => $buyer->id,
                'seller_user_id' => $asset->user_id,
                'amount'         => $amountPoisha,
                'quantity'       => $quantity,
                'buyer_message'  => $message,
                'status'         => 'pending',
                'expires_at'     => now()->addHours($validityHours),
            ]);
        });
    }

    /**
     * Seller accepts an offer. Creates a payment-pending state.
     */
    public function accept(Offer $offer, User $seller): void
    {
        abort_unless($offer->seller_user_id === $seller->id, 403);
        abort_unless($offer->isPending(), 422, 'Offer is no longer pending.');
        abort_if($offer->isExpired(), 422, 'This offer has expired and cannot be accepted.');
        abort_unless($offer->asset->status->value === 'published', 422, 'Listing is no longer available.');
        abort_if($offer->asset->isSoldOut(), 422, 'Listing is sold out.');

        DB::transaction(function () use ($offer) {
            $offer->update([
                'status'       => OfferStatus::Accepted,
                'responded_at' => now(),
            ]);
            // Price lock persists — listing.isPriceLocked() reads activeOffers (pending),
            // but accepted offer also locks until payment or expiry. Future Order module consumes this.
        });
    }

    /**
     * Seller rejects an offer.
     */
    public function reject(Offer $offer, User $seller): void
    {
        abort_unless($offer->seller_user_id === $seller->id, 403);
        abort_unless($offer->isPending(), 422, 'Offer cannot be rejected in its current state.');

        $offer->update([
            'status'       => OfferStatus::Rejected,
            'responded_at' => now(),
            'rejected_at'  => now(),
        ]);
    }

    /**
     * Mark all expired pending offers as expired.
     * Called by the artisan command and also inline before critical actions.
     */
    public function expireStale(): int
    {
        return Offer::where('status', 'pending')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired', 'expired_at' => now()]);
    }

    /**
     * Expire a single offer inline (called before accept to prevent race conditions).
     */
    public function enforceExpiry(Offer $offer): void
    {
        if ($offer->isPending() && $offer->isExpired()) {
            $offer->update(['status' => 'expired', 'expired_at' => now()]);
            abort(422, 'This offer has expired.');
        }
    }
}
