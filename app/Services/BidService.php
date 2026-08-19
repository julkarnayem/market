<?php
namespace App\Services;

use App\Enums\AssetStatus;
use App\Enums\BidStatus;
use App\Models\Asset;
use App\Models\Bid;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Public bidding on single/unique listings.
 *
 * Two rules carry all the weight here, and both are enforced server-side —
 * hiding a button in Vue is never the gate:
 *
 *   1. Only a Single listing can be bid on. Multiple and Unlimited listings
 *      reject bid creation outright.
 *   2. A new bid must be strictly higher than the current top bid. That is the
 *      *only* minimum: no fixed increment, no "you already hold the top bid",
 *      no "not two in a row". A user may bid against their own bid.
 *
 * Exactly one bid on a listing is Active at a time — the top one. Lower bids
 * are Outbid. If the top bid is cancelled or rejected, the highest remaining
 * Outbid bid is promoted back to Active, so "current top bid" is always the
 * single Active row and never a stale amount.
 *
 * Reads and writes happen under a row lock on the listing (see place()), so
 * two simultaneous bids cannot both pass the "beats the top bid" check.
 */
class BidService
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * Place a bid on a listing.
     *
     * @param int $amountPoisha integer poisha, never a float
     */
    public function place(User $bidder, Asset $asset, int $amountPoisha): Bid
    {
        abort_if($bidder->id === $asset->user_id, 403, 'You cannot bid on your own listing.');
        abort_unless($bidder->canTransact(), 403, 'Your account is not in good standing.');
        abort_if($amountPoisha <= 0, 422, 'Bid amount must be greater than zero.');

        [$bid, $outbidUserId, $outbidAmount] = DB::transaction(function () use ($bidder, $asset, $amountPoisha) {
            // Lock the listing first: every bid on it serialises through this row.
            $locked = Asset::whereKey($asset->id)->lockForUpdate()->firstOrFail();

            $this->assertBiddable($locked);

            $top = $this->lockedTopBid($locked->id);

            // Not abort_if(): its message argument is evaluated eagerly, so
            // formatting $top->amount would blow up on the first bid, when
            // there is no top bid to beat.
            if ($top !== null && $amountPoisha <= (int) $top->amount) {
                abort(422, 'Your bid must be higher than the current top bid of ' . Money::format((int) $top->amount) . '.');
            }

            $bid = Bid::create([
                'asset_id'       => $locked->id,
                'bidder_user_id' => $bidder->id,
                'seller_user_id' => $locked->user_id,
                'amount'         => $amountPoisha,
                'status'         => BidStatus::Active->value,
            ]);

            // The new bid is now the top one; everything else drops to outbid.
            Bid::where('asset_id', $locked->id)
                ->where('id', '!=', $bid->id)
                ->where('status', BidStatus::Active->value)
                ->update(['status' => BidStatus::Outbid->value, 'outbid_at' => now()]);

            return [$bid, $top?->bidder_user_id, $top?->amount];
        });

        $this->announcePlacement($bid, $asset, $outbidUserId ? (int) $outbidUserId : null, $outbidAmount ? (int) $outbidAmount : null);

        return $bid;
    }

    /**
     * Seller accepts a bid.
     *
     * The listing moves to "Bid Accepted", which is explicitly *not* Sold:
     * Buy Now and New Bid both close, the page stays visible, and only the
     * winning bidder's payment carries it forward.
     */
    public function accept(Bid $bid, User $seller): Bid
    {
        abort_unless((int) $bid->seller_user_id === $seller->id, 403, 'Only the seller can accept a bid.');

        $accepted = DB::transaction(function () use ($bid) {
            $asset = Asset::whereKey($bid->asset_id)->lockForUpdate()->firstOrFail();
            $fresh = Bid::whereKey($bid->id)->lockForUpdate()->firstOrFail();

            abort_unless($fresh->status === BidStatus::Active, 422, 'This bid is no longer active.');
            abort_if($asset->accepted_bid_id !== null, 422, 'A bid has already been accepted on this listing.');
            abort_unless($asset->status === AssetStatus::Published, 422, 'This listing is not available.');

            $fresh->update([
                'status'      => BidStatus::Accepted->value,
                'accepted_at' => now(),
            ]);

            // The seller picked one bid, so the rest lose.
            Bid::where('asset_id', $asset->id)
                ->where('id', '!=', $fresh->id)
                ->whereIn('status', [BidStatus::Active->value, BidStatus::Outbid->value])
                ->update(['status' => BidStatus::Rejected->value, 'rejected_at' => now()]);

            $asset->update([
                'status'          => AssetStatus::BidAccepted->value,
                'accepted_bid_id' => $fresh->id,
            ]);

            return $fresh->refresh();
        });

        $this->announceAcceptance($accepted);

        return $accepted;
    }

    /** Seller rejects a single bid without accepting another. */
    public function reject(Bid $bid, User $seller): Bid
    {
        abort_unless((int) $bid->seller_user_id === $seller->id, 403, 'Only the seller can reject a bid.');

        $rejected = DB::transaction(function () use ($bid) {
            Asset::whereKey($bid->asset_id)->lockForUpdate()->firstOrFail();
            $fresh = Bid::whereKey($bid->id)->lockForUpdate()->firstOrFail();

            abort_unless(
                in_array($fresh->status, [BidStatus::Active, BidStatus::Outbid], true),
                422,
                'This bid can no longer be rejected.'
            );

            $fresh->update(['status' => BidStatus::Rejected->value, 'rejected_at' => now()]);
            $this->promoteNextTopBid((int) $fresh->asset_id);

            return $fresh->refresh();
        });

        $bidder = $rejected->bidder;
        if ($bidder) {
            $this->notifications->inApp(
                $bidder,
                'bid_rejected',
                'Bid declined',
                'Your bid of ' . Money::format((int) $rejected->amount) . ' was declined.',
                ['bid_id' => $rejected->id, 'asset_id' => $rejected->asset_id],
            );
        }

        return $rejected;
    }

    /** A bidder withdraws their own bid. */
    public function cancel(Bid $bid, User $bidder): Bid
    {
        abort_unless((int) $bid->bidder_user_id === $bidder->id, 403, 'You can only cancel your own bid.');

        return DB::transaction(function () use ($bid) {
            Asset::whereKey($bid->asset_id)->lockForUpdate()->firstOrFail();
            $fresh = Bid::whereKey($bid->id)->lockForUpdate()->firstOrFail();

            abort_unless(
                in_array($fresh->status, [BidStatus::Active, BidStatus::Outbid], true),
                422,
                'This bid can no longer be cancelled.'
            );

            $fresh->update(['status' => BidStatus::Cancelled->value, 'cancelled_at' => now()]);
            $this->promoteNextTopBid((int) $fresh->asset_id);

            return $fresh->refresh();
        });
    }

    /**
     * Close out open bids once a listing is no longer buyable — someone bought
     * it outright, or the seller pulled it. Called from the order flow.
     */
    public function expireOpenBids(int $assetId, ?int $exceptBidId = null): int
    {
        return Bid::where('asset_id', $assetId)
            ->when($exceptBidId, fn ($q) => $q->where('id', '!=', $exceptBidId))
            ->whereIn('status', [BidStatus::Active->value, BidStatus::Outbid->value])
            ->update(['status' => BidStatus::Expired->value, 'updated_at' => now()]);
    }

    /**
     * Server-side gate for bidding. Deliberately duplicated by the policy and
     * the controller: the button being hidden in Vue proves nothing.
     */
    private function assertBiddable(Asset $asset): void
    {
        abort_unless(
            $asset->inventoryType()->allowsBidding(),
            422,
            'Bidding is only available on single-item listings.'
        );
        abort_if($asset->accepted_bid_id !== null, 422, 'A bid has already been accepted on this listing.');
        abort_unless($asset->status === AssetStatus::Published, 422, 'This listing is not available.');
        abort_if($asset->isSoldOut(), 422, 'This listing is sold out.');
    }

    private function lockedTopBid(int $assetId): ?Bid
    {
        return Bid::where('asset_id', $assetId)
            ->where('status', BidStatus::Active->value)
            ->orderByDesc('amount')
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();
    }

    /**
     * Keep "exactly one Active bid = the top bid" true after the top bid is
     * withdrawn or rejected, by promoting the highest remaining outbid one.
     */
    private function promoteNextTopBid(int $assetId): void
    {
        if (Bid::where('asset_id', $assetId)->where('status', BidStatus::Active->value)->exists()) {
            return;
        }

        $next = Bid::where('asset_id', $assetId)
            ->where('status', BidStatus::Outbid->value)
            ->orderByDesc('amount')
            ->orderByDesc('id')
            ->first();

        $next?->update(['status' => BidStatus::Active->value, 'outbid_at' => null]);
    }

    private function announcePlacement(Bid $bid, Asset $asset, ?int $outbidUserId, ?int $outbidAmount): void
    {
        $seller = $asset->seller;
        if ($seller) {
            $this->notifications->inApp(
                $seller,
                'bid_placed',
                'New bid received',
                Money::format((int) $bid->amount) . ' was bid on "' . $asset->title . '".',
                ['bid_id' => $bid->id, 'asset_id' => $asset->id],
            );
        }

        if ($outbidUserId === null || $outbidUserId === (int) $bid->bidder_user_id) {
            return;
        }

        $previous = User::find($outbidUserId);
        if ($previous) {
            $this->notifications->inApp(
                $previous,
                'bid_outbid',
                'You have been outbid',
                'Your bid of ' . Money::format((int) $outbidAmount) . ' on "' . $asset->title . '" was outbid.',
                ['asset_id' => $asset->id],
            );
        }
    }

    private function announceAcceptance(Bid $bid): void
    {
        $bidder = $bid->bidder;
        if (!$bidder) {
            return;
        }

        $this->notifications->inApp(
            $bidder,
            'bid_accepted',
            'Your bid was accepted',
            'Your bid of ' . Money::format((int) $bid->amount) . ' was accepted. Complete payment to secure the item.',
            ['bid_id' => $bid->id, 'asset_id' => $bid->asset_id],
        );
    }
}
