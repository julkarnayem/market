<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class SellerVerification extends Model
{
    protected $fillable = [
        'user_id','document_type','nid_number','date_of_birth',
        'selfie_path','document_path','document_back_path','status',
        'rejection_reason','admin_notes','reviewed_by',
        'reviewed_at','submitted_at','attempt_number',
    ];
    protected $casts = [
        'reviewed_at'=>'datetime','submitted_at'=>'datetime','date_of_birth'=>'date',
        'attempt_number'=>'integer',
    ];
    // Encrypt NID at rest
    protected function nidNumber(): Attribute {
        return Attribute::make(
            get: fn($v) => $v ? decrypt($v) : null,
            set: fn($v) => $v ? encrypt($v) : null,
        );
    }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(User::class,'reviewed_by'); }
    public function isPending(): bool { return $this->status === 'pending'; }
    public function isApproved(): bool { return $this->status === 'approved'; }
    public function isRejected(): bool { return $this->status === 'rejected'; }
}
