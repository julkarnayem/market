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
        'rejected_at','rejection_reason','cancelled_at',
        'processed_at','completed_by','external_reference',
        'wallet_transaction_id',
    ];
    protected function casts(): array {
        return [
            'amount'=>'integer','fee'=>'integer','net_amount'=>'integer',
            'reviewed_at'=>'datetime',
            'rejected_at'=>'datetime','cancelled_at'=>'datetime','processed_at'=>'datetime',
            'status'=>WithdrawalStatus::class,
        ];
    }

    protected static function booted(): void
    {
        // Mint the reference's random half once, up front, for every path that
        // creates a withdrawal (service, factory, direct create). The token does
        // not depend on the id, so it is safe to set before insert.
        static::creating(function (self $withdrawal) {
            if (empty($withdrawal->reference_token)) {
                $withdrawal->reference_token = self::generateReferenceToken();
            }
        });
    }

    public function user(): BelongsTo      { return $this->belongsTo(User::class); }
    public function reviewer(): BelongsTo  { return $this->belongsTo(User::class,'reviewed_by'); }
    public function completer(): BelongsTo { return $this->belongsTo(User::class,'completed_by'); }

    /**
     * The buyer-facing handle, so support can be given something to quote:
     * WD-{id}{TOKEN}, e.g. WD-7RASRSC42JFW. The id keeps it unique; the token is
     * a stored random suffix so it cannot be guessed from the sequence alone.
     */
    public function reference(): string
    {
        return 'WD-' . (int) $this->id . (string) $this->reference_token;
    }

    /**
     * The random half of the reference. Uppercase A–Z and digits so it reads
     * cleanly over the phone; the id in front guarantees uniqueness, so this
     * needs no collision check.
     */
    public static function generateReferenceToken(int $length = 10): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $token    = '';
        for ($i = 0; $i < $length; $i++) {
            $token .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $token;
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

    /**
     * The payout destination, in full. Only the account owner (their own
     * withdrawal history) and staff (the admin list + detail page) ever see a
     * withdrawal, and staff cannot send money to a masked number — so there is
     * no third party to hide it from, and nothing is masked.
     */
    public function fullAccount(): string
    {
        if ($this->method === 'bank') {
            return trim(($this->bank_name ?? '') . ' ' . (string) $this->bank_account_number);
        }

        return (string) $this->mfs_number;
    }

    /** Only the user's own pending request is theirs to withdraw. */
    public function isCancellable(): bool
    {
        return $this->status === WithdrawalStatus::Pending;
    }
}
