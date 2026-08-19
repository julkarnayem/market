<?php
namespace App\Services;

use App\Models\Asset;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Buyer↔seller conversations.
 *
 * "Contact Seller" is not a system of its own — it opens the ordinary chat with
 * the listing attached as context. Opening it twice must not leave the buyer
 * with two threads about the same listing, so forListing() reuses an existing
 * conversation for the same (buyer, seller, listing) triple.
 */
class ConversationService
{
    /**
     * The buyer↔seller conversation about a listing, created only if the pair
     * does not already have one.
     */
    public function forListing(User $buyer, Asset $asset): Conversation
    {
        abort_if($buyer->id === $asset->user_id, 403, 'You cannot message yourself.');
        abort_unless($buyer->canTransact(), 403, 'Your account is not in good standing.');
        abort_unless($asset->status->isPubliclyVisible(), 422, 'This listing is not available.');

        return DB::transaction(function () use ($buyer, $asset) {
            $existing = $this->findForListing($buyer->id, (int) $asset->user_id, (int) $asset->id);
            if ($existing) {
                return $existing;
            }

            $conversation = Conversation::create([
                'type'            => 'direct',
                'asset_id'        => $asset->id,
                'status'          => 'open',
                'last_message_at' => now(),
            ]);

            $conversation->participants()->attach([$buyer->id, $asset->user_id]);

            return $conversation;
        });
    }

    /**
     * An existing listing conversation between these two users, if any. Also
     * matches the order conversation for the same listing, so a buyer who has
     * already ordered keeps talking in the thread they know.
     */
    public function findForListing(int $buyerId, int $sellerId, int $assetId): ?Conversation
    {
        return Conversation::query()
            ->where(fn ($q) => $q
                ->where('asset_id', $assetId)
                ->orWhereHas('order', fn ($o) => $o->where('asset_id', $assetId)))
            ->whereHas('participants', fn ($q) => $q->where('users.id', $buyerId))
            ->whereHas('participants', fn ($q) => $q->where('users.id', $sellerId))
            ->orderBy('id')
            ->first();
    }

    /** The other participant in a two-party conversation. */
    public function counterpart(Conversation $conversation, int $userId): ?User
    {
        return $conversation->participants()->where('users.id', '!=', $userId)->first();
    }
}
