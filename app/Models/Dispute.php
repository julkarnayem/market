<?php
namespace App\Models;

use App\Enums\DisputeMessageType;
use App\Enums\DisputeReason;
use App\Enums\DisputeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A dispute belongs to an ORDER. The order is the source of truth for what was
 * bought, for how much, and from whom — the dispute never re-derives any of it,
 * and is never attached to a listing, a bid or a chat message.
 */
class Dispute extends Model
{
    protected $fillable = [
        'order_id', 'opened_by', 'reference', 'reason_code', 'description', 'status',
        'seller_responded_at', 'escalated_at', 'last_activity_at',
        'resolution', 'resolution_type', 'resolution_amount', 'resolution_note',
        'resolved_by', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolution_amount'   => 'integer',
            'seller_responded_at' => 'datetime',
            'escalated_at'        => 'datetime',
            'last_activity_at'    => 'datetime',
            'resolved_at'         => 'datetime',
            'status'              => DisputeStatus::class,
            'reason_code'         => DisputeReason::class,
        ];
    }

    public function order(): BelongsTo    { return $this->belongsTo(Order::class); }
    public function opener(): BelongsTo   { return $this->belongsTo(User::class, 'opened_by'); }
    public function resolver(): BelongsTo { return $this->belongsTo(User::class, 'resolved_by'); }

    /** The whole thread, internal notes included — admin screens only. */
    public function messages(): HasMany
    {
        return $this->hasMany(DisputeMessage::class)->oldest();
    }

    /** What buyer and seller are allowed to read. */
    public function publicMessages(): HasMany
    {
        return $this->messages()->where('is_internal', false);
    }

    /** Staff-only commentary. Never rendered outside the admin screen. */
    public function internalNotes(): HasMany
    {
        return $this->messages()->where('is_internal', true);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(DisputeEvidence::class)->latest();
    }

    public function resolutions(): HasMany
    {
        return $this->hasMany(DisputeResolution::class)->latest();
    }

    /** The proposal currently on the table, if any. Only ever one. */
    public function pendingResolution(): ?DisputeResolution
    {
        return $this->resolutions()->where('status', 'proposed')->first();
    }

    public function isActive(): bool     { return $this->status->isActive(); }
    public function isResolvable(): bool { return $this->status->isResolvable(); }

    /** The ceiling on any refund: what the buyer actually paid. */
    public function maxRefundable(): int
    {
        return (int) ($this->order->buyer_total ?? 0);
    }

    /** Buyer, seller and opener — the non-admin parties to this dispute. */
    public function isParty(User $user): bool
    {
        $order = $this->order;

        return $order !== null
            && in_array($user->id, [(int) $order->buyer_user_id, (int) $order->seller_user_id], true);
    }

    /** 'buyer', 'seller', 'admin' — or null for someone with no business here. */
    public function roleOf(User $user): ?string
    {
        $order = $this->order;

        return match (true) {
            $order !== null && (int) $order->buyer_user_id === $user->id  => 'buyer',
            $order !== null && (int) $order->seller_user_id === $user->id => 'seller',
            $user->isAdmin()                                             => 'admin',
            default                                                      => null,
        };
    }

    /**
     * Staff capability, asked independently of party membership.
     *
     * roleOf() answers "which seat does this person occupy in the thread", and it
     * deliberately resolves a party before staff: someone who bought the order is
     * the buyer there, even if they also work here. Whether someone may act as
     * STAFF is a different question, and it must not be answered by roleOf() —
     * a staff member who is also a party would come back 'buyer' and be locked out
     * of every administrative action on that dispute.
     *
     * The capability itself is the permission model's business, not a relationship
     * to this row, which is why it reads the same `disputes.manage` permission the
     * admin controller gates on.
     */
    public function isStaff(User $user): bool
    {
        return $user->isAdmin() || $user->hasPermission('disputes.manage');
    }

    /**
     * Reference for a dispute created before the column existed, or one being
     * built right now. Stored on create, so this is only a fallback.
     */
    public function displayReference(): string
    {
        return $this->reference ?: 'D-' . (10000 + (int) $this->id);
    }

    public function hasInternalNotes(): bool
    {
        return $this->messages()
            ->where('type', DisputeMessageType::InternalNote->value)
            ->exists();
    }
}
