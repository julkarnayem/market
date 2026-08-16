<?php
namespace App\Models;

use App\Enums\AssetStatus;
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
        'price','quantity','available_quantity','sold_quantity','views_count',
        'status','is_featured','featured_start_at','featured_end_at',
        'reviewed_by','reviewed_at','rejection_reason',
        'admin_notes','changes_requested_note','policy_accepted_at',
    ];
    protected function casts(): array {
        return [
            'price'=>'integer','quantity'=>'integer',
            'available_quantity'=>'integer','sold_quantity'=>'integer','views_count'=>'integer',
            'is_featured'=>'boolean',
            'featured_start_at'=>'datetime','featured_end_at'=>'datetime',
            'reviewed_at'=>'datetime','policy_accepted_at'=>'datetime',
            'status'=>AssetStatus::class,
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
    public function orders(): HasMany     { return $this->hasMany(Order::class); }
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
    public function isSoldOut(): bool        { return $this->available_quantity <= 0; }
    public function isFeaturedNow(): bool    { return $this->is_featured && $this->featured_start_at?->isPast() && $this->featured_end_at?->isFuture(); }
    public function isAvailableForPurchase(): bool { return $this->status === AssetStatus::Published && !$this->isSoldOut(); }
}
