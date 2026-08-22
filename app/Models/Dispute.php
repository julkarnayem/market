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

    protected static function booted(): void
    {
        // Mint the reference's random half up front. It does not depend on the id,
        // so every path that creates a dispute — the service, a seeder, a direct
        // create — gets one without having to remember to ask.
        static::creating(function (self $dispute) {
            if (empty($dispute->reference_token)) {
                $dispute->reference_token = self::generateReferenceToken();
            }
        });

        // The id half can only be known once the row exists. This lives here
        // rather than in DisputeService because the reference is now what the view
        // URL resolves on: a dispute created by any other path still has to be
        // addressable.
        static::created(function (self $dispute) {
            if (empty($dispute->reference)) {
                $dispute->reference = 'D-' . (int) $dispute->id . (string) $dispute->reference_token;
                $dispute->saveQuietly();
            }
        });
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
     * The party-facing handle: D-{id}{TOKEN}, e.g. D-4Q9WJ5NXR7TB. Same shape as
     * Withdrawal::reference() — the id keeps it unique, the stored random token
     * stops the sequence being guessable. Written on create, so the fallback here
     * only covers a row being built right now.
     */
    public function displayReference(): string
    {
        return $this->reference ?: 'D-' . (int) $this->id . (string) $this->reference_token;
    }

    /**
     * The random half of the reference. Uppercase A–Z and digits so it reads
     * cleanly over the phone and needs no URL escaping; the id in front
     * guarantees uniqueness, so this needs no collision check.
     */
    public static function generateReferenceToken(int $length = 10): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $token    = '';
        for ($i = 0; $i < $length; $i++) {
            $token .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $token;
    }

    /**
     * The two segments of this dispute's view URL. The order number is there
     * because it is what the parties actually quote to each other; the reference
     * is what resolves the row, since an order may hold more than one dispute
     * over its life and so cannot identify one on its own.
     *
     * The placeholder for a dispute with no order is a plain hyphen rather than
     * the em dash used for display, so the URL stays ASCII.
     */
    public function viewRouteParams(): array
    {
        return [
            'orderNumber' => $this->order?->order_number ?: '-',
            'dispute'     => $this->displayReference(),
        ];
    }

    public function hasInternalNotes(): bool
    {
        return $this->messages()
            ->where('type', DisputeMessageType::InternalNote->value)
            ->exists();
    }
}
