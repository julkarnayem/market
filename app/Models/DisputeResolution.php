<?php
namespace App\Models;

use App\Enums\DisputeResolutionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A proposed or executed outcome for a dispute.
 *
 * Buyer and seller propose here while they are negotiating; an admin decision is
 * recorded here too, pre-accepted, so §15's audit trail (who decided what, for
 * how much, when, and why) and the negotiation history are the same record.
 *
 * `executed_at` is the idempotency marker: it is stamped inside the same
 * transaction that moves the money, so a replayed accept finds it already set
 * and moves nothing a second time.
 */
class DisputeResolution extends Model
{
    public const STATUS_PROPOSED  = 'proposed';
    public const STATUS_ACCEPTED  = 'accepted';
    public const STATUS_DECLINED  = 'declined';
    public const STATUS_WITHDRAWN = 'withdrawn';

    protected $fillable = [
        'dispute_id', 'proposed_by', 'role', 'type', 'amount', 'note',
        'status', 'responded_by', 'responded_at', 'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'       => 'integer',
            'responded_at' => 'datetime',
            'executed_at'  => 'datetime',
            'type'         => DisputeResolutionType::class,
        ];
    }

    public function dispute(): BelongsTo  { return $this->belongsTo(Dispute::class); }
    public function proposer(): BelongsTo { return $this->belongsTo(User::class, 'proposed_by'); }
    public function responder(): BelongsTo { return $this->belongsTo(User::class, 'responded_by'); }

    public function isPending(): bool  { return $this->status === self::STATUS_PROPOSED; }
    public function isExecuted(): bool { return $this->executed_at !== null; }

    /** The other party — the one whose accept or decline this is waiting on. */
    public function awaitingRole(): string
    {
        return $this->role === 'buyer' ? 'seller' : 'buyer';
    }
}
