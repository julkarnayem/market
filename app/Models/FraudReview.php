<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FraudReview extends Model
{
    protected $fillable = ['user_id','status','risk_score','risk_flags','admin_notes','reviewed_by','reviewed_at'];
    protected $casts    = ['risk_score' => 'integer', 'risk_flags' => 'array', 'reviewed_at' => 'datetime'];

    public function user(): BelongsTo     { return $this->belongsTo(User::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class,'reviewed_by'); }
}
