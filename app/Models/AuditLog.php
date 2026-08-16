<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'user_id','action','module','auditable_type','auditable_id',
        'old_values','new_values','reason','ip_address','user_agent','created_at',
    ];
    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function actor(): BelongsTo  { return $this->belongsTo(User::class,'user_id'); }
    // Keep legacy alias
    public function user(): BelongsTo   { return $this->belongsTo(User::class,'user_id'); }
    public function auditable(): MorphTo { return $this->morphTo(); }
}
