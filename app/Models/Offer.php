<?php
namespace App\Models;

use App\Enums\OfferStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Offer extends Model
{
    protected $fillable = [
        'asset_id','buyer_user_id','seller_user_id',
        'amount','quantity','status',
        'buyer_message','expires_at','responded_at','rejected_at','expired_at',
    ];
    protected function casts(): array {
        return [
            'amount'       => 'integer',
            'quantity'     => 'integer',
            'expires_at'   => 'datetime',
            'responded_at' => 'datetime',
            'rejected_at'  => 'datetime',
            'expired_at'   => 'datetime',
            'status'       => OfferStatus::class,
        ];
    }

    public function asset(): BelongsTo   { return $this->belongsTo(Asset::class); }
    public function buyer(): BelongsTo   { return $this->belongsTo(User::class, 'buyer_user_id'); }
    public function seller(): BelongsTo  { return $this->belongsTo(User::class, 'seller_user_id'); }

    public function isExpired(): bool    { return $this->expires_at?->isPast() ?? false; }
    public function isPending(): bool    { return $this->status === OfferStatus::Pending; }
    public function isAccepted(): bool   { return $this->status === OfferStatus::Accepted; }
    public function timeRemainingSeconds(): int { return max(0, now()->diffInSeconds($this->expires_at, false)); }

    // Scopes
    public function scopePending($q)    { return $q->where('status','pending'); }
    public function scopeActive($q)     { return $q->where('status','pending')->where('expires_at','>',now()); }
    public function scopeExpired($q)    { return $q->where('status','pending')->where('expires_at','<=',now()); }
}
