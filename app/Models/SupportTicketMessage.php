<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class SupportTicketMessage extends Model
{
    protected $fillable = [
        'support_ticket_id','user_id','body','is_internal_note',
        'attachment_path','attachment_disk','attachment_name','is_staff_reply',
    ];
    protected $casts = ['is_staff_reply' => 'boolean', 'is_internal_note' => 'boolean'];
    protected $hidden = ['attachment_path'];

    public function ticket(): BelongsTo { return $this->belongsTo(SupportTicket::class,'support_ticket_id'); }
    public function user(): BelongsTo   { return $this->belongsTo(User::class); }
    public function hasAttachment(): bool { return (bool) $this->attachment_path; }
}
