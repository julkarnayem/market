<?php
namespace App\Models;

use App\Enums\OfferStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A custom offer, made inside a buyer↔seller conversation.
 *
 * Either party can create one; whoever did *not* create it responds. In both
 * directions the buyer is the one who pays, so `buyer_user_id` is the payer
 * regardless of `created_by_user_id`.
 *
 * Custom offers are private to their conversation and never appear in a
 * listing's public bid history — bids and offers are separate systems.
 */
class Offer extends Model
{
    protected $fillable = [
        'asset_id','conversation_id','message_id',
        'buyer_user_id','seller_user_id','created_by_user_id',
        'amount','quantity','delivery_days','status',
        'buyer_message','expires_at','responded_at','rejected_at','expired_at',
        'paid_at','completed_at',
    ];
    protected function casts(): array {
        return [
            'amount'         => 'integer',
            'quantity'       => 'integer',
            'delivery_days'  => 'integer',
            'expires_at'     => 'datetime',
            'responded_at'   => 'datetime',
            'rejected_at'    => 'datetime',
            'expired_at'     => 'datetime',
            'paid_at'        => 'datetime',
            'completed_at'   => 'datetime',
            'status'         => OfferStatus::class,
        ];
    }

    public function asset(): BelongsTo   { return $this->belongsTo(Asset::class); }
    public function conversation(): BelongsTo { return $this->belongsTo(Conversation::class); }
    public function message(): BelongsTo { return $this->belongsTo(Message::class); }
    public function buyer(): BelongsTo   { return $this->belongsTo(User::class, 'buyer_user_id'); }
    public function seller(): BelongsTo  { return $this->belongsTo(User::class, 'seller_user_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by_user_id'); }

    public function isExpired(): bool    { return $this->expires_at?->isPast() ?? false; }
    public function isPending(): bool    { return $this->status === OfferStatus::Pending; }
    public function isAccepted(): bool   { return $this->status === OfferStatus::Accepted; }
    public function timeRemainingSeconds(): int { return max(0, now()->diffInSeconds($this->expires_at, false)); }

    /** The seller made it when they are the creator; otherwise the buyer did. */
    public function wasCreatedBySeller(): bool
    {
        return (int) $this->created_by_user_id === (int) $this->seller_user_id;
    }

    /** The party expected to accept or decline: whoever did not create it. */
    public function responderId(): int
    {
        return $this->wasCreatedBySeller() ? (int) $this->buyer_user_id : (int) $this->seller_user_id;
    }

    public function isCreator(int $userId): bool
    {
        return (int) $this->created_by_user_id === $userId;
    }

    public function isResponder(int $userId): bool
    {
        return $this->responderId() === $userId;
    }

    /** Only ever the buyer — a seller never pays for their own accepted offer. */
    public function isPayer(int $userId): bool
    {
        return (int) $this->buyer_user_id === $userId;
    }

    // Scopes
    public function scopePending($q)    { return $q->where('status','pending'); }
    public function scopeActive($q)     { return $q->where('status','pending')->where('expires_at','>',now()); }
    public function scopeExpired($q)    { return $q->where('status','pending')->where('expires_at','<=',now()); }
}
