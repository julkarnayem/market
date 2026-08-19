<?php
namespace App\Models;

use App\Enums\BidStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A public bid on a single/unique listing.
 *
 * Bids are not chat messages and never appear in a conversation — they render
 * in the listing's own bid history. The only rule for a new bid is that it
 * beats the current top bid; see BidService for the locking that enforces it.
 */
class Bid extends Model
{
    protected $fillable = [
        'asset_id', 'bidder_user_id', 'seller_user_id',
        'amount', 'status',
        'accepted_at', 'rejected_at', 'cancelled_at', 'outbid_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => BidStatus::class,
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'outbid_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo { return $this->belongsTo(Asset::class); }
    public function bidder(): BelongsTo { return $this->belongsTo(User::class, 'bidder_user_id'); }
    public function seller(): BelongsTo { return $this->belongsTo(User::class, 'seller_user_id'); }

    public function isActive(): bool { return $this->status === BidStatus::Active; }
    public function isAccepted(): bool { return $this->status === BidStatus::Accepted; }
    public function isExpired(): bool { return $this->expires_at?->isPast() ?? false; }

    public function scopeActive($q) { return $q->where('status', BidStatus::Active->value); }

    /** Highest first, then newest first so equal amounts cannot both be "top". */
    public function scopeTopFirst($q) { return $q->orderByDesc('amount')->orderByDesc('id'); }
}
