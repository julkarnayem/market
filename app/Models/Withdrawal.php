<?php
namespace App\Models;

use App\Enums\WithdrawalMethod;
use App\Enums\WithdrawalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Withdrawal extends Model
{
    protected $fillable = [
        'user_id','client_request_id','amount','fee','net_amount','currency','method',
        'mfs_provider','mfs_number',
        'bank_account_name','bank_account_number','bank_name','bank_branch',
        'status',
        'reviewed_by','reviewed_at','note',
        'approved_by','approved_at','rejected_at','rejection_reason','cancelled_at',
        'processed_at','completed_by','external_reference',
        'wallet_transaction_id',
    ];
    protected function casts(): array {
        return [
            'amount'=>'integer','fee'=>'integer','net_amount'=>'integer',
            'reviewed_at'=>'datetime','approved_at'=>'datetime',
            'rejected_at'=>'datetime','cancelled_at'=>'datetime','processed_at'=>'datetime',
            'status'=>WithdrawalStatus::class,
        ];
    }

    public function user(): BelongsTo      { return $this->belongsTo(User::class); }
    public function reviewer(): BelongsTo  { return $this->belongsTo(User::class,'reviewed_by'); }
    public function approver(): BelongsTo  { return $this->belongsTo(User::class,'approved_by'); }
    public function completer(): BelongsTo { return $this->belongsTo(User::class,'completed_by'); }

    /** The buyer-facing handle, so support can be given something to quote. */
    public function reference(): string
    {
        return 'WD-' . (10000 + (int) $this->id);
    }

    /**
     * The method the user picked, recovered from the pair of columns that record
     * it: `method` says mfs-or-bank, `mfs_provider` says which wallet.
     */
    public function methodEnum(): ?WithdrawalMethod
    {
        if ($this->method === 'bank') {
            return WithdrawalMethod::Bank;
        }

        return WithdrawalMethod::tryFrom((string) $this->mfs_provider);
    }

    public function methodLabel(): string
    {
        return $this->methodEnum()?->label() ?? strtoupper((string) ($this->mfs_provider ?: $this->method));
    }

    /** Masked MFS number for display (e.g. 01*****789) */
    public function maskedNumber(): string
    {
        return self::mask($this->mfs_number ?? '');
    }

    /**
     * What the destination account is, masked. Never render the raw account
     * number — this is the only accessor any payload should use.
     */
    public function maskedAccount(): string
    {
        if ($this->method === 'bank') {
            return trim(($this->bank_name ?? '') . ' ' . self::mask((string) $this->bank_account_number));
        }

        return $this->maskedNumber();
    }

    /** Only the user's own pending request is theirs to withdraw. */
    public function isCancellable(): bool
    {
        return $this->status === WithdrawalStatus::Pending;
    }

    private static function mask(string $value): string
    {
        if (strlen($value) <= 6) return $value;

        return substr($value, 0, 2) . str_repeat('*', strlen($value) - 5) . substr($value, -3);
    }
}
