<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FraudEvent extends Model
{
    protected $fillable = ['user_id','signal','score_impact','context','ip_address'];
    protected $casts    = ['score_impact' => 'integer'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
