<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'conversation_id','sender_user_id','message_type','body','metadata',
        'reply_to_id','client_message_id','attachment_path','attachment_disk',
        'attachment_name','attachment_mime','is_system',
    ];
    protected $hidden  = ['attachment_path'];
    protected $casts   = ['metadata'=>'array','is_system'=>'boolean'];

    public function conversation(): BelongsTo { return $this->belongsTo(Conversation::class); }
    public function sender(): BelongsTo       { return $this->belongsTo(User::class,'sender_user_id'); }
    public function replyTo(): BelongsTo      { return $this->belongsTo(Message::class,'reply_to_id'); }

    public function isText(): bool       { return $this->message_type === 'text'; }
    public function isSystem(): bool     { return $this->is_system; }
    public function isDeleted(): bool    { return $this->trashed(); }
    public function hasAttachment(): bool { return (bool) $this->attachment_path; }

    /** Safe escaped body — never render raw HTML. */
    public function safeBody(): string
    {
        return e($this->body ?? '');
    }
}
