<?php
namespace App\Models;

use App\Enums\AssetStatus;
use App\Enums\InventoryType;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id','category_id','title','slug','description',
        'price','inventory_type','quantity','available_quantity','sold_quantity',
        'accepted_bid_id','views_count',
        'status','is_featured','featured_start_at','featured_end_at',
        'reviewed_by','reviewed_at','rejection_reason',
        'admin_notes','changes_requested_note','policy_accepted_at',
    ];
    protected function casts(): array {
        return [
            'price'=>'integer','quantity'=>'integer',
            'available_quantity'=>'integer','sold_quantity'=>'integer','views_count'=>'integer',
            'accepted_bid_id'=>'integer',
            'is_featured'=>'boolean',
            'featured_start_at'=>'datetime','featured_end_at'=>'datetime',
            'reviewed_at'=>'datetime','policy_accepted_at'=>'datetime',
            'status'=>AssetStatus::class,
            'inventory_type'=>InventoryType::class,
        ];
    }

    // Relations
    public function seller(): BelongsTo   { return $this->belongsTo(User::class, 'user_id'); }
    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
    public function images(): HasMany     { return $this->hasMany(AssetImage::class)->orderBy('sort_order'); }
    public function coverImage(): HasOne  { return $this->hasOne(AssetImage::class)->where('is_cover',true)->orderBy('sort_order'); }
    public function attributeValues(): HasMany { return $this->hasMany(AssetAttributeValue::class)->with('attribute'); }
    public function edits(): HasMany      { return $this->hasMany(AssetEdit::class)->latest(); }
    public function pendingEdit(): HasOne { return $this->hasOne(AssetEdit::class)->where('status','pending_edit_approval')->latest(); }
    public function offers(): HasMany     { return $this->hasMany(Offer::class); }
    public function activeOffers(): HasMany { return $this->hasMany(Offer::class)->where('status','pending')->where('expires_at','>',now()); }
    public function bids(): HasMany        { return $this->hasMany(Bid::class); }
    public function activeBids(): HasMany  { return $this->hasMany(Bid::class)->where('status','active'); }
    public function acceptedBid(): BelongsTo { return $this->belongsTo(Bid::class, 'accepted_bid_id'); }
    public function orders(): HasMany     { return $this->hasMany(Order::class); }
    // Buyer reviews of this listing. Unrelated to `reviewed_by`/`reviewed_at`,
    // which record an admin's moderation of the listing itself.
    public function reviews(): HasMany    { return $this->hasMany(Review::class); }
    public function promotions(): HasMany { return $this->hasMany(Promotion::class); }
    public function favorites(): HasMany  { return $this->hasMany(Favorite::class); }
    public function views(): HasMany      { return $this->hasMany(AssetView::class); }

    // Scopes
    public function scopePublished($q)     { return $q->where('status', AssetStatus::Published); }
    public function scopeFeaturedNow($q)   {
        return $q->where('is_featured', true)
            ->where('featured_start_at', '<=', now())
            ->where('featured_end_at', '>=', now());
    }

    // Guards
    public function hasActiveOrder(): bool   { return $this->orders()->whereIn('status',['paid','delivered','disputed'])->exists(); }
    public function hasActiveOffer(): bool   { return $this->activeOffers()->exists(); }
    public function hasPendingEdit(): bool   { return $this->pendingEdit()->exists(); }
    public function isEditable(): bool       { return !$this->hasActiveOrder(); }
    public function isPriceLocked(): bool    { return $this->hasActiveOffer(); }
    public function isFeaturedNow(): bool    { return $this->is_featured && $this->featured_start_at?->isPast() && $this->featured_end_at?->isFuture(); }

    // Inventory
    public function inventoryType(): InventoryType
    {
        return $this->inventory_type ?? InventoryType::Single;
    }

    public function isUnlimited(): bool { return $this->inventoryType() === InventoryType::Unlimited; }

    /** Unlimited stock never runs out, so it can never be sold out. */
    public function isSoldOut(): bool
    {
        return !$this->isUnlimited() && $this->available_quantity <= 0;
    }

    public function isAvailableForPurchase(): bool
    {
        return $this->status === AssetStatus::Published && !$this->isSoldOut();
    }

    /**
     * Re-derive the stock counters from the orders that actually hold stock.
     *
     * Stock is taken at payment and, until now, never given back: an order that
     * was fully refunded left `available_quantity` at zero and the listing stuck
     * on `sold_out` for good. Rather than incrementing back — which would drift
     * from reality after any manual correction — this recomputes both counters
     * from the listing's own orders, so the answer is always "quantity minus what
     * is genuinely sold". That is also what makes the multi-order case right: a
     * refund only frees the listing when no other valid sale is left holding it.
     *
     * Call this after any change to an order's status that alters whether it
     * counts as a sale.
     */
    public function syncAvailabilityFromOrders(): void
    {
        $sold = (int) $this->orders()
            ->whereIn('status', OrderStatus::saleValues())
            ->sum('quantity');

        $updates = ['sold_quantity' => $sold];

        // Unlimited listings hold no stock and are never sold out, so only the
        // counter above means anything for them.
        if ($this->inventoryType()->consumesInventory()) {
            $available = max(0, (int) $this->quantity - $sold);
            $updates['available_quantity'] = $available;

            // Only the two states that payment and refund own are flipped here.
            // Draft, paused, suspended, archived, rejected, pending review and
            // bid_accepted all mean something this method has no business
            // overwriting.
            if ($available > 0 && $this->status === AssetStatus::SoldOut) {
                $updates['status'] = AssetStatus::Published;
            } elseif ($available <= 0 && $this->status === AssetStatus::Published) {
                $updates['status'] = AssetStatus::SoldOut;
            }
        }

        $this->update($updates);
    }

    /**
     * Bidding is Single-only, and closes once a bid has been accepted.
     * The controller/service re-check this — the UI is never the gate.
     */
    public function allowsBidding(): bool
    {
        return $this->inventoryType()->allowsBidding()
            && $this->status === AssetStatus::Published
            && !$this->isSoldOut();
    }

    public function hasAcceptedBid(): bool
    {
        return $this->accepted_bid_id !== null;
    }

    /** Highest live bid, or null when nobody has bid yet. */
    public function topBid(): ?Bid
    {
        return $this->bids()->active()->topFirst()->first();
    }

    public function topBidAmount(): ?int
    {
        $max = $this->bids()->active()->max('amount');

        return $max === null ? null : (int) $max;
    }
}
