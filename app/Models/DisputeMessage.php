<?php
namespace App\Models;

use App\Enums\DisputeMessageType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One row in a dispute thread.
 *
 * Buyer, seller and admin all write here — this is not the buyer↔seller order
 * conversation and must never be merged into it. Rows with is_internal are staff
 * notes: DisputeService is the only thing that reads them, and only for admins.
 */
class DisputeMessage extends Model
{
    protected $fillable = [
        'dispute_id', 'user_id', 'type', 'role', 'body', 'metadata',
        'is_internal', 'client_message_id',
    ];

    protected function casts(): array
    {
        return [
            'metadata'    => 'array',
            'is_internal' => 'boolean',
            'type'        => DisputeMessageType::class,
        ];
    }

    public function dispute(): BelongsTo { return $this->belongsTo(Dispute::class); }
    public function author(): BelongsTo  { return $this->belongsTo(User::class, 'user_id'); }

    /** Set when this message carried an evidence upload. */
    public function evidence(): HasOne
    {
        return $this->hasOne(DisputeEvidence::class, 'message_id');
    }

    public function isSystem(): bool
    {
        return in_array($this->type, [DisputeMessageType::System, DisputeMessageType::AdminDecision], true);
    }

    /** Bodies are rendered as text, never as markup. */
    public function safeBody(): string
    {
        return e((string) ($this->body ?? ''));
    }
}
