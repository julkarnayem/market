<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Promotion extends Model
{
    protected $fillable = [
        'asset_id','user_id','seller_id','days','price','currency',
        'starts_at','ends_at','status','payment_status','payment_reference',
        'wallet_transaction_id','is_manual','created_by',
        'featured_by','admin_featured_at','admin_unfeatured_at','admin_note','warning_sent_at',
    ];
    protected $casts = [
        'price'=>'integer','days'=>'integer','is_manual'=>'boolean',
        'starts_at'=>'datetime','ends_at'=>'datetime',
        'admin_featured_at'=>'datetime','admin_unfeatured_at'=>'datetime','warning_sent_at'=>'datetime',
    ];

    public function asset(): BelongsTo      { return $this->belongsTo(Asset::class); }
    public function seller(): BelongsTo     { return $this->belongsTo(User::class,'seller_id'); }
    public function createdBy(): BelongsTo  { return $this->belongsTo(User::class,'created_by'); }
    public function featuredBy(): BelongsTo { return $this->belongsTo(User::class,'featured_by'); }

    /** Is this promotion currently live? (status + time check — no stale data trust) */
    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->starts_at?->isPast()
            && $this->ends_at?->isFuture();
    }

    /** Scope: only genuinely active promotions right now */
    public function scopeCurrentlyActive(Builder $q): Builder
    {
        return $q->where('status','active')
            ->where('starts_at','<=',now())
            ->where('ends_at','>',now());
    }

    /** Scope: needs expiry warning (within 24h, warning not yet sent) */
    public function scopeNeedsExpiryWarning(Builder $q): Builder
    {
        return $q->where('status','active')
            ->where('ends_at','<=',now()->addHours(24))
            ->where('ends_at','>',now())
            ->whereNull('warning_sent_at');
    }
}
