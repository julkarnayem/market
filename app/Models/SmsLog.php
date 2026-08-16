<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsLog extends Model
{
    protected $fillable = [
        'user_id','phone','template','message','provider','provider_reference',
        'status','error_message','attempts','idempotency_key','sent_at','failed_at',
    ];
    protected $casts = ['sent_at'=>'datetime','failed_at'=>'datetime','attempts'=>'integer'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    /** Masked phone for safe display */
    public function maskedPhone(): string
    {
        $p = $this->phone;
        return strlen($p) > 6 ? substr($p,0,3).str_repeat('*',strlen($p)-6).substr($p,-3) : $p;
    }
}
