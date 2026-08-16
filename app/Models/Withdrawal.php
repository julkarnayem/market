<?php
namespace App\Models;

use App\Enums\WithdrawalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdrawal extends Model
{
    protected $fillable = [
        'user_id','amount','fee','net_amount','currency','method',
        'mfs_provider','mfs_number','status',
        'reviewed_by','reviewed_at','note',
        'approved_by','approved_at','rejected_at','rejection_reason',
        'processed_at','completed_by','external_reference',
        'wallet_transaction_id',
    ];
    protected function casts(): array {
        return [
            'amount'=>'integer','fee'=>'integer','net_amount'=>'integer',
            'reviewed_at'=>'datetime','approved_at'=>'datetime',
            'rejected_at'=>'datetime','processed_at'=>'datetime',
            'status'=>WithdrawalStatus::class,
        ];
    }

    public function user(): BelongsTo      { return $this->belongsTo(User::class); }
    public function reviewer(): BelongsTo  { return $this->belongsTo(User::class,'reviewed_by'); }
    public function approver(): BelongsTo  { return $this->belongsTo(User::class,'approved_by'); }
    public function completer(): BelongsTo { return $this->belongsTo(User::class,'completed_by'); }

    /** Masked MFS number for display (e.g. 01*****789) */
    public function maskedNumber(): string
    {
        $n = $this->mfs_number ?? '';
        if (strlen($n) <= 6) return $n;
        return substr($n,0,2) . str_repeat('*', strlen($n)-5) . substr($n,-3);
    }
}
