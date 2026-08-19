<?php
namespace App\Services;

use App\Enums\AssetStatus;
use App\Enums\InventoryType;
use App\Enums\OfferStatus;
use App\Models\Asset;
use App\Models\Conversation;
use App\Models\Offer;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Custom offers — the private, in-chat negotiation.
 *
 * These replace the old listing-level "Make an Offer". Everything about them is
 * scoped to a conversation:
 *
 *  - Created inside a buyer↔seller chat and visible only there. They never
 *    appear in a listing's public bid history; bids and custom offers are
 *    separate systems that do not read each other.
 *  - Available on all three inventory types, unlike bids.
 *  - Either party may create one. Whoever did *not* create it accepts or
 *    declines — but the buyer is always the one who pays, in both directions.
 *    A seller never gets a Pay Now button for their own accepted offer.
 *  - Accepting does not mark the listing Sold. Payment does.
 */
class OfferService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly MessageService $messages,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Create a custom offer inside a conversation.
     *
     * @param int $amountPoisha integer poisha for the whole offer's unit price
     */
    public function createInConversation(
        Conversation $conversation,
        User $creator,
        int $amountPoisha,
        int $quantity = 1,
        ?int $deliveryDays = null,
        ?string $note = null,
    ): Offer {
        abort_unless($conversation->hasParticipant($creator->id), 403, 'You are not part of this conversation.');
        abort_unless($creator->canTransact(), 403, 'Your account is not in good standing.');
        abort_if($amountPoisha <= 0, 422, 'Offer amount must be greater than zero.');
        abort_if($quantity < 1, 422, 'Quantity must be at least 1.');

        $asset = $conversation->contextAsset();
        abort_if($asset === null, 422, 'This conversation is not about a listing, so an offer cannot be made.');

        [$buyerId, $sellerId] = $this->partiesFor($conversation, $asset);

        $validityHours = $this->settings->offerValidityHours();

        $offer = DB::transaction(function () use (
            $conversation, $creator, $asset, $amountPoisha, $quantity,
            $deliveryDays, $note, $buyerId, $sellerId, $validityHours
        ) {
            $locked = Asset::whereKey($asset->id)->lockForUpdate()->firstOrFail();

            $this->assertOfferable($locked, $quantity);

            // One live offer per person per conversation: the other party can
            // still counter while yours is open, but you cannot stack your own.
            $mine = Offer::where('conversation_id', $conversation->id)
                ->where('created_by_user_id', $creator->id)
                ->where('status', OfferStatus::Pending->value)
                ->where('expires_at', '>', now())
                ->exists();
            abort_if($mine, 422, 'You already have a pending offer in this conversation.');

            $offer = Offer::create([
                'asset_id'           => $locked->id,
                'conversation_id'    => $conversation->id,
                'buyer_user_id'      => $buyerId,
                'seller_user_id'     => $sellerId,
                'created_by_user_id' => $creator->id,
                'amount'             => $amountPoisha,
                'quantity'           => $quantity,
                'delivery_days'      => $deliveryDays,
                'buyer_message'      => $note,
                'status'             => OfferStatus::Pending->value,
                'expires_at'         => now()->addHours($validityHours),
            ]);

            // The card the chat renders. Attributed to the creator so it sits on
            // their side of the thread like any other message they sent.
            $message = $this->messages->sendCard(
                $conversation,
                $creator,
                'custom_offer',
                'Custom offer: ' . Money::format($amountPoisha),
                ['offer_id' => $offer->id],
            );

            $offer->update(['message_id' => $message->id]);

            return $offer->refresh();
        });

        $this->notifyCounterpart(
            $offer,
            $creator,
            'custom_offer_received',
            'New custom offer',
            Money::format((int) $offer->amount) . ' offered for "' . $asset->title . '".',
        );

        return $offer;
    }

    /**
     * Accept a custom offer. Only the party who did not create it may accept,
     * and acceptance never marks the listing Sold — it moves the offer to
     * awaiting-payment, and only the buyer can pay.
     */
    public function accept(Offer $offer, User $user): Offer
    {
        abort_unless($offer->isResponder($user->id), 403, 'Only the other party can accept this offer.');
        $this->enforceExpiry($offer);
        abort_unless($offer->isPending(), 422, 'This offer is no longer pending.');

        $accepted = DB::transaction(function () use ($offer) {
            $asset = Asset::whereKey($offer->asset_id)->lockForUpdate()->firstOrFail();
            $fresh = Offer::whereKey($offer->id)->lockForUpdate()->firstOrFail();

            abort_unless($fresh->isPending(), 422, 'This offer is no longer pending.');
            $this->assertOfferable($asset, (int) $fresh->quantity);

            $fresh->update([
                'status'       => OfferStatus::Accepted->value,
                'responded_at' => now(),
            ]);

            // Any other open offer in the thread is superseded.
            Offer::where('conversation_id', $fresh->conversation_id)
                ->where('id', '!=', $fresh->id)
                ->where('status', OfferStatus::Pending->value)
                ->update(['status' => OfferStatus::Cancelled->value, 'responded_at' => now()]);

            return $fresh->refresh();
        });

        $this->postStatusCard($accepted, $user, 'accepted this offer');

        $this->notifyCounterpart(
            $accepted,
            $user,
            'custom_offer_accepted',
            'Custom offer accepted',
            'Your offer of ' . Money::format((int) $accepted->amount) . ' was accepted.',
        );

        return $accepted;
    }

    /** Decline a custom offer. Only the party who did not create it may decline. */
    public function reject(Offer $offer, User $user): Offer
    {
        abort_unless($offer->isResponder($user->id), 403, 'Only the other party can decline this offer.');
        abort_unless($offer->isPending(), 422, 'This offer can no longer be declined.');

        $offer->update([
            'status'       => OfferStatus::Rejected->value,
            'responded_at' => now(),
            'rejected_at'  => now(),
        ]);

        $offer->refresh();
        $this->postStatusCard($offer, $user, 'declined this offer');

        $this->notifyCounterpart(
            $offer,
            $user,
            'custom_offer_rejected',
            'Custom offer declined',
            'Your offer of ' . Money::format((int) $offer->amount) . ' was declined.',
        );

        return $offer;
    }

    /** Withdraw your own pending offer. */
    public function cancel(Offer $offer, User $user): Offer
    {
        abort_unless($offer->isCreator($user->id), 403, 'Only the sender can withdraw this offer.');
        abort_unless($offer->isPending(), 422, 'This offer can no longer be withdrawn.');

        $offer->update([
            'status'       => OfferStatus::Cancelled->value,
            'responded_at' => now(),
        ]);

        $offer->refresh();
        $this->postStatusCard($offer, $user, 'withdrew this offer');

        return $offer;
    }

    /** Mark all pending offers past their expiry as expired. */
    public function expireStale(): int
    {
        return Offer::where('status', OfferStatus::Pending->value)
            ->where('expires_at', '<=', now())
            ->update(['status' => OfferStatus::Expired->value, 'expired_at' => now()]);
    }

    /** Expire one offer inline, so an accept cannot slip past the deadline. */
    public function enforceExpiry(Offer $offer): void
    {
        if ($offer->isPending() && $offer->isExpired()) {
            $offer->update(['status' => OfferStatus::Expired->value, 'expired_at' => now()]);
            abort(422, 'This offer has expired.');
        }
    }

    /**
     * Which participant is buying and which is selling.
     *
     * Derived from the listing owner, never from the request — a crafted payload
     * cannot make someone else the seller or put the offer on another listing.
     *
     * @return array{0:int,1:int} [buyerId, sellerId]
     */
    private function partiesFor(Conversation $conversation, Asset $asset): array
    {
        $sellerId = (int) $asset->user_id;

        abort_unless(
            $conversation->hasParticipant($sellerId),
            422,
            'The listing owner is not part of this conversation.'
        );

        $buyerId = (int) $conversation->participants()
            ->where('users.id', '!=', $sellerId)
            ->value('users.id');

        abort_if($buyerId === 0, 422, 'This conversation has no buyer.');

        return [$buyerId, $sellerId];
    }

    /**
     * A listing can carry a custom offer on every inventory type. What differs
     * is stock: Single needs its one item free, Multiple needs enough left, and
     * Unlimited never runs out.
     */
    private function assertOfferable(Asset $asset, int $quantity): void
    {
        abort_unless($asset->status === AssetStatus::Published, 422, 'This listing is not available.');

        if ($asset->inventoryType() === InventoryType::Unlimited) {
            return;
        }

        abort_if($asset->isSoldOut(), 422, 'This listing is sold out.');

        if ($asset->inventoryType() === InventoryType::Single) {
            abort_if($quantity !== 1, 422, 'This listing is a single item, so the quantity must be 1.');

            return;
        }

        abort_if(
            $quantity > (int) $asset->available_quantity,
            422,
            'Only ' . $asset->available_quantity . ' left in stock.'
        );
    }

    /** A short system line in the thread so both sides see what happened. */
    private function postStatusCard(Offer $offer, User $actor, string $what): void
    {
        $conversation = $offer->conversation;
        if (!$conversation) {
            return;
        }

        $this->messages->sendCard(
            $conversation,
            $actor,
            'custom_offer_event',
            $actor->name . ' ' . $what . ' (' . Money::format((int) $offer->amount) . ').',
            ['offer_id' => $offer->id, 'status' => $offer->status->value],
        );
    }

    private function notifyCounterpart(Offer $offer, User $actor, string $type, string $title, string $body): void
    {
        $otherId = (int) $offer->buyer_user_id === $actor->id
            ? (int) $offer->seller_user_id
            : (int) $offer->buyer_user_id;

        $other = User::find($otherId);
        if (!$other) {
            return;
        }

        $this->notifications->inApp($other, $type, $title, $body, [
            'offer_id'        => $offer->id,
            'conversation_id' => $offer->conversation_id,
        ]);
    }
}
