<?php
namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Asset;
use App\Models\Promotion;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PromotionService
{
    /** Server-side whitelist: days → poisha */
    public const PRICES = [1=>5000, 2=>10000, 3=>15000, 4=>20000, 5=>25000];

    public function __construct(
        private readonly WalletService       $wallet,
        private readonly NotificationService $notifs,
        private readonly AuditLogger         $audit,
    ) {}

    /**
     * Purchase a paid promotion from seller's available wallet balance.
     * Idempotent: checks for active overlapping promotion.
     */
    public function purchase(Asset $asset, User $seller, int $days): Promotion
    {
        // Server-side eligibility
        abort_unless(array_key_exists($days, self::PRICES), 422, 'Invalid promotion duration.');
        abort_unless($asset->user_id === $seller->id, 403, 'You do not own this listing.');
        abort_unless($seller->canTransact(), 403, 'Your account is restricted.');
        abort_unless($asset->status->value === 'published', 422, 'Only published listings can be promoted.');

        $pricePoisha = self::PRICES[$days];

        return DB::transaction(function () use ($asset, $seller, $days, $pricePoisha) {
            // Idempotency: prevent overlapping paid promotions
            $existing = Promotion::where('asset_id', $asset->id)
                ->where('status','active')
                ->where('ends_at','>',now())
                ->lockForUpdate()->first();

            abort_if($existing, 422, 'Listing already has an active promotion until ' . $existing->ends_at->format('d M Y, H:i'));

            $startsAt = now();
            $endsAt   = $startsAt->copy()->addDays($days);

            $promotion = Promotion::create([
                'asset_id'       => $asset->id,
                'user_id'        => $seller->id,
                'seller_id'      => $seller->id,
                'days'           => $days,
                'price'          => $pricePoisha,
                'currency'       => 'BDT',
                'starts_at'      => $startsAt,
                'ends_at'        => $endsAt,
                'status'         => 'active',
                'payment_status' => 'paid',
                'is_manual'      => false,
                'created_by'     => $seller->id,
            ]);

            // Debit wallet (validates balance atomically)
            $tx = $this->wallet->debitAvailable(
                $seller, $pricePoisha,
                TransactionType::PromotionPurchase,
                $promotion,
                "{$days}-day promotion for '{$asset->title}'"
            );
            $promotion->update(['wallet_transaction_id' => $tx->id]);

            $this->audit->log('promotion.purchased', $promotion, [], [
                'days' => $days, 'price' => $pricePoisha,
            ]);

            $this->notifs->notify(
                $seller, 'promotion_purchased',
                'Promotion activated!',
                "{$days}-day promotion for '{$asset->title}' is now active until " . $endsAt->format('d M Y, H:i'),
                ['promotion_id' => $promotion->id, 'asset_id' => $asset->id],
                'promotion_purchased',
                ['days' => $days, 'listing_title' => $asset->title, 'end_date' => $endsAt->format('d M Y')]
            );

            return $promotion;
        });
    }

    /**
     * Expire all promotions past their end_at. Idempotent.
     */
    public function expireStale(): int
    {
        $promotions = Promotion::where('status', 'active')
            ->where('ends_at', '<=', now())
            ->get();

        $count = 0;
        foreach ($promotions as $promo) {
            DB::transaction(function () use ($promo, &$count) {
                $promo = Promotion::where('id', $promo->id)
                    ->where('status', 'active')
                    ->lockForUpdate()->first();
                if (!$promo) return;

                $promo->update(['status' => 'expired']);
                $this->audit->log('promotion.expired', $promo);

                if ($promo->seller_id) {
                    $seller = $promo->seller;
                    if ($seller) {
                        $this->notifs->notify(
                            $seller, 'promotion_expired',
                            'Promotion expired',
                            "Your promotion for '{$promo->asset->title}' has ended.",
                            ['promotion_id' => $promo->id],
                            'promotion_expired',
                            ['listing_title' => $promo->asset->title ?? '']
                        );
                    }
                }
                $count++;
            });
        }
        return $count;
    }

    /**
     * Send 24h expiry warnings. Idempotent via warning_sent_at.
     */
    public function sendExpiryWarnings(): int
    {
        $promotions = Promotion::with('seller','asset')
            ->needsExpiryWarning()->get();

        foreach ($promotions as $promo) {
            if (!$promo->seller) continue;
            $this->notifs->notify(
                $promo->seller, 'promotion_expiring',
                'Promotion expiring soon',
                "Your promotion for '{$promo->asset->title}' expires " . $promo->ends_at->diffForHumans(),
                ['promotion_id' => $promo->id],
                'promotion_expiring',
                ['listing_title' => $promo->asset->title ?? '']
            );
            $promo->update(['warning_sent_at' => now()]);
        }
        return $promotions->count();
    }

    /**
     * Admin manually features a listing (no charge).
     */
    public function adminFeature(Asset $asset, User $admin, \Carbon\Carbon $endsAt, string $note = ''): Promotion
    {
        return DB::transaction(function () use ($asset, $admin, $endsAt, $note) {
            $promotion = Promotion::create([
                'asset_id'         => $asset->id,
                'user_id'          => $asset->user_id,
                'seller_id'        => $asset->user_id,
                'days'             => 0,
                'price'            => 0,
                'currency'         => 'BDT',
                'starts_at'        => now(),
                'ends_at'          => $endsAt,
                'status'           => 'active',
                'payment_status'   => 'paid',
                'is_manual'        => true,
                'featured_by'      => $admin->id,
                'admin_featured_at'=> now(),
                'admin_note'       => $note,
                'created_by'       => $admin->id,
            ]);
            $this->audit->log('promotion.admin_featured', $promotion, [], ['admin_note'=>$note]);
            $this->notifs->inApp($asset->user, 'promotion_manually_featured',
                'Your listing has been featured by Admin',
                "Your listing '{$asset->title}' has been manually featured.");
            return $promotion;
        });
    }

    /**
     * Admin unfeatures a listing. Does NOT refund — use refund system separately.
     */
    public function adminUnfeature(Promotion $promotion, User $admin, string $note = ''): void
    {
        $promotion->update([
            'status'              => 'cancelled',
            'admin_unfeatured_at' => now(),
            'admin_note'          => $note ?: $promotion->admin_note,
        ]);
        $this->audit->log('promotion.admin_unfeatured', $promotion);
        if ($promotion->seller_id) {
            $this->notifs->inApp($promotion->seller, 'promotion_manually_unfeatured',
                'Listing feature ended',
                "Admin has ended the feature on '{$promotion->asset->title}'.");
        }
    }
}
