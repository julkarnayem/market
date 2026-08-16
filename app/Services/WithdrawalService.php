<?php
namespace App\Services;

use App\Enums\TransactionType;
use App\Enums\WithdrawalStatus;
use App\Models\User;
use App\Models\Withdrawal;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class WithdrawalService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly WalletService   $wallet,
        private readonly AuditLogger     $audit,
    ) {}

    /**
     * Buyer/seller requests a withdrawal.
     * Deducts from available balance immediately (reserve).
     */
    public function request(User $user, int $amountPoisha, string $mfsProvider, string $mfsNumber): Withdrawal
    {
        $feePoisha = $this->settings->withdrawalFee();
        $minPoisha = $this->settings->minWithdrawal();
        $netPoisha = $amountPoisha - $feePoisha;

        abort_if($amountPoisha < $minPoisha, 422, 'Minimum withdrawal is ' . Money::format($minPoisha));
        abort_if($netPoisha <= 0, 422, 'Net amount after fee must be positive.');

        $wallet = $user->wallet;
        abort_unless($wallet && $wallet->canWithdraw($amountPoisha), 422, 'Insufficient available balance.');

        return DB::transaction(function () use ($user, $amountPoisha, $feePoisha, $netPoisha, $mfsProvider, $mfsNumber) {
            // Reserve full gross from available balance
            $withdrawal = Withdrawal::create([
                'user_id'     => $user->id,
                'amount'      => $amountPoisha,
                'fee'         => $feePoisha,
                'net_amount'  => $netPoisha,
                'currency'    => 'BDT',
                'method'      => 'mfs',
                'mfs_provider'=> $mfsProvider,
                'mfs_number'  => $mfsNumber,
                'status'      => WithdrawalStatus::Pending,
            ]);

            // Debit gross — fee and net reserved together
            $tx = $this->wallet->debitAvailable($user, $amountPoisha, TransactionType::WithdrawalReserve, $withdrawal,
                "Withdrawal request #{$withdrawal->id} — reserved");

            $withdrawal->update(['wallet_transaction_id' => $tx->id]);
            $this->audit->log('withdrawal.requested', $withdrawal);

            return $withdrawal;
        });
    }

    /**
     * Admin approves withdrawal — marks it ready for processing.
     */
    public function approve(Withdrawal $withdrawal, User $admin): void
    {
        abort_unless($withdrawal->status === WithdrawalStatus::Pending, 422, 'Withdrawal is not pending.');

        $withdrawal->update([
            'status'      => WithdrawalStatus::Approved,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);
        $this->audit->log('withdrawal.approved', $withdrawal);
    }

    /**
     * Admin rejects withdrawal — returns funds to user's available balance.
     */
    public function reject(Withdrawal $withdrawal, User $admin, string $reason): void
    {
        abort_unless(in_array($withdrawal->status, [WithdrawalStatus::Pending, WithdrawalStatus::Approved], true),
            422, 'Cannot reject this withdrawal.');

        DB::transaction(function () use ($withdrawal, $admin, $reason) {
            // Return the GROSS amount to available balance
            $this->wallet->creditAvailable($withdrawal->user, $withdrawal->amount,
                TransactionType::WithdrawalReturn, $withdrawal,
                "Withdrawal #{$withdrawal->id} rejected — funds returned");

            $withdrawal->update([
                'status'           => WithdrawalStatus::Rejected,
                'reviewed_by'      => $admin->id,
                'reviewed_at'      => now(),
                'rejected_at'      => now(),
                'rejection_reason' => $reason,
            ]);
            $this->audit->log('withdrawal.rejected', $withdrawal);
        });
    }

    /**
     * Admin marks payout as completed (MFS transfer done).
     * Fee is the platform's revenue — no further wallet credit needed.
     */
    public function complete(Withdrawal $withdrawal, User $admin, string $externalReference = ''): void
    {
        abort_unless($withdrawal->status === WithdrawalStatus::Approved, 422, 'Withdrawal must be approved first.');

        $withdrawal->update([
            'status'             => WithdrawalStatus::Completed,
            'completed_by'       => $admin->id,
            'processed_at'       => now(),
            'external_reference' => $externalReference,
        ]);
        $this->audit->log('withdrawal.completed', $withdrawal, [], ['external_ref' => $externalReference]);
    }
}
