<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessageReport extends Model
{
    protected $fillable = ['message_id','reporter_id','reason','description','status','reviewed_by','reviewed_at'];
    protected $casts    = ['reviewed_at'=>'datetime'];

    public function message(): BelongsTo   { return $this->belongsTo(Message::class); }
    public function reporter(): BelongsTo  { return $this->belongsTo(User::class,'reporter_id'); }
    public function reviewer(): BelongsTo  { return $this->belongsTo(User::class,'reviewed_by'); }
}
