<?php
namespace App\Models;

use App\Enums\DisputeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dispute extends Model
{
    protected $fillable = [
        'order_id','opened_by','reason','description','status',
        'resolution','resolution_type','resolution_amount',
        'admin_notes','resolved_by','resolved_at','resolution_note',
    ];
    protected function casts(): array {
        return [
            'resolution_amount' => 'integer',
            'resolved_at'       => 'datetime',
            'status'            => DisputeStatus::class,
        ];
    }

    public function order(): BelongsTo    { return $this->belongsTo(Order::class); }
    public function opener(): BelongsTo   { return $this->belongsTo(User::class, 'opened_by'); }
    public function resolver(): BelongsTo { return $this->belongsTo(User::class, 'resolved_by'); }
    public function evidence(): HasMany   { return $this->hasMany(DisputeEvidence::class)->latest(); }

    public function isResolvable(): bool  { return $this->status->isOpen(); }
    public function maxRefundable(): int  { return $this->order->buyer_total ?? 0; }
}
