<?php
namespace App\Models;

use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportTicket extends Model
{
    protected $fillable = [
        'reference','user_id','order_id','asset_id','withdrawal_id','category','subject','priority','status',
        'assigned_to','resolved_at','closed_at','last_reply_at',
        'assigned_at','resolution_note',
    ];
    protected $casts = [
        'status'      => TicketStatus::class,
        'resolved_at' => 'datetime',
        'closed_at'   => 'datetime',
        'last_reply_at' => 'datetime',
        'assigned_at' => 'datetime',
    ];

    public function order(): BelongsTo      { return $this->belongsTo(Order::class); }
    public function asset(): BelongsTo      { return $this->belongsTo(Asset::class); }
    public function withdrawal(): BelongsTo { return $this->belongsTo(Withdrawal::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function assignee(): BelongsTo { return $this->belongsTo(User::class,'assigned_to'); }
    public function messages(): HasMany   { return $this->hasMany(SupportTicketMessage::class)->oldest(); }
    public function latestMessage(): HasMany { return $this->hasMany(SupportTicketMessage::class)->latest()->limit(1); }

    public function isOpen(): bool   { return in_array($this->status, [TicketStatus::Open, TicketStatus::InProgress, TicketStatus::WaitingForUser]); }
    public function isClosed(): bool { return in_array($this->status, [TicketStatus::Resolved, TicketStatus::Closed]); }

    public function priorityColor(): string {
        return match($this->priority) {
            'urgent' => 'rose', 'high' => 'amber', 'normal' => 'brand', default => 'slate',
        };
    }
}
