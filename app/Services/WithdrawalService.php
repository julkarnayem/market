<?php
namespace App\Services;

use App\Enums\TransactionType;
use App\Enums\WithdrawalMethod;
use App\Enums\WithdrawalStatus;
use App\Models\User;
use App\Models\Withdrawal;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Payouts out of a user's available balance.
 *
 * THE FINANCIAL RULE, stated once: the GROSS amount leaves the wallet when the
 * request is made, and the fee is taken out of it — a ৳1,000 request with a ৳20
 * fee debits ৳1,000 and pays out ৳980, so the user loses exactly what they asked
 * to withdraw and the fee is platform revenue. The wallet is never debited
 * ৳1,020. A rejection or cancellation returns the same GROSS ৳1,000.
 *
 * Every state change re-reads the row under lockForUpdate() *inside* the
 * transaction and re-checks the status there. That is what makes each one
 * idempotent: two admins clicking Mark-paid, or a double-submitted Reject,
 * serialize on the lock and the loser finds a status it may no longer act on.
 * Without it the status check ran against a stale read and a second rejection
 * would credit the balance a second time — money out of nothing.
 */
class WithdrawalService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly WalletService   $wallet,
        private readonly AuditLogger     $audit,
    ) {}

    /**
     * Buyer/seller requests a withdrawal. Reserves the gross amount immediately,
     * so the same balance cannot back two requests.
     *
     * @param array<string,string|null> $accountDetails the chosen method's fields
     */
    public function request(
        User             $user,
        int              $amountPoisha,
        WithdrawalMethod $method,
        array            $accountDetails,
        ?string          $clientRequestId = null,
    ): Withdrawal {
        $feePoisha = $this->settings->withdrawalFee();
        $minPoisha = $this->settings->minWithdrawal();
        $netPoisha = $amountPoisha - $feePoisha;

        abort_if($amountPoisha <= 0, 422, 'Withdrawal amount must be positive.');
        abort_if($amountPoisha < $minPoisha, 422, 'Minimum withdrawal is ' . Money::format($minPoisha));
        abort_if($netPoisha <= 0, 422, 'Net amount after fee must be positive.');

        // A double-submitted form returns the request it already made rather than
        // reserving the balance twice.
        if ($clientRequestId !== null) {
            $existing = Withdrawal::where('user_id', $user->id)
                ->where('client_request_id', $clientRequestId)->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        // Cheap pre-check for a clear error message. Queried through the relation,
        // not read off $user->wallet, which may be a stale cached copy from earlier
        // in the request. The authoritative check is debitAvailable() below, which
        // re-reads the balance under a row lock — that is what actually stops two
        // concurrent requests overdrawing.
        $wallet = $user->wallet()->first();
        abort_unless(
            $wallet !== null && $wallet->canWithdraw($amountPoisha),
            422,
            'Insufficient available balance.',
        );

        return DB::transaction(function () use (
            $user, $amountPoisha, $feePoisha, $netPoisha, $method, $accountDetails, $clientRequestId
        ) {
            $withdrawal = Withdrawal::create(array_merge([
                'user_id'           => $user->id,
                'client_request_id' => $clientRequestId,
                'amount'            => $amountPoisha,
                'fee'               => $feePoisha,
                'net_amount'        => $netPoisha,
                'currency'          => 'BDT',
                'method'            => $method->storageKey(),
                // Mobile money records which wallet; a bank transfer leaves it null.
                'mfs_provider'      => $method->isMobileMoney() ? $method->value : null,
                'status'            => WithdrawalStatus::Pending,
            ], $accountDetails));

            // Gross out of available. Aborts here — including a losing race —
            // roll the withdrawal row back with it.
            $tx = $this->wallet->debitAvailable(
                $user, $amountPoisha, TransactionType::WithdrawalReserve, $withdrawal,
                "Withdrawal {$withdrawal->reference()} — reserved",
            );

            $withdrawal->update(['wallet_transaction_id' => $tx->id]);
            $this->audit->log('withdrawal.requested', $withdrawal);

            return $withdrawal;
        });
    }

    /**
     * Admin rejects — the reserved gross returns to the user's available balance,
     * exactly once, with its own ledger entry. The reserve entry is left alone so
     * the trail still shows what was held and when.
     */
    public function reject(Withdrawal $withdrawal, User $admin, string $reason): void
    {
        DB::transaction(function () use ($withdrawal, $admin, $reason) {
            $locked = Withdrawal::whereKey($withdrawal->getKey())->lockForUpdate()->firstOrFail();

            abort_unless(
                $locked->status === WithdrawalStatus::Pending,
                422,
                'Cannot reject this withdrawal.',
            );

            $this->wallet->creditAvailable(
                $locked->user, (int) $locked->amount,
                TransactionType::WithdrawalReturn, $locked,
                "Withdrawal {$locked->reference()} rejected — funds returned",
            );

            $locked->update([
                'status'           => WithdrawalStatus::Rejected,
                'reviewed_by'      => $admin->id,
                'reviewed_at'      => now(),
                'rejected_at'      => now(),
                'rejection_reason' => $reason,
            ]);
            $this->audit->log('withdrawal.rejected', $locked);
        });

        $withdrawal->refresh();
    }

    /**
     * The user takes back their own pending request. Same reversal as a rejection —
     * the gross returns with its own ledger entry — but attributed to them.
     */
    public function cancel(Withdrawal $withdrawal, User $user): void
    {
        abort_unless((int) $withdrawal->user_id === $user->id, 403, 'This is not your withdrawal.');

        DB::transaction(function () use ($withdrawal, $user) {
            $locked = Withdrawal::whereKey($withdrawal->getKey())->lockForUpdate()->firstOrFail();

            // Re-checked under the lock: once staff have paid or rejected it,
            // it is no longer the user's to cancel.
            abort_unless($locked->isCancellable(), 422, 'Only a pending withdrawal can be cancelled.');
            abort_unless((int) $locked->user_id === $user->id, 403, 'This is not your withdrawal.');

            $this->wallet->creditAvailable(
                $locked->user, (int) $locked->amount,
                TransactionType::WithdrawalReturn, $locked,
                "Withdrawal {$locked->reference()} cancelled — funds returned",
            );

            $locked->update([
                'status'       => WithdrawalStatus::Cancelled,
                'cancelled_at' => now(),
            ]);
            $this->audit->log('withdrawal.cancelled', $locked);
        });

        $withdrawal->refresh();
    }

    /**
     * Admin pays the request out and marks it done in one step. The gross already
     * left the wallet at request time and the fee is platform revenue, so no
     * further wallet movement happens here — this only records that the money was
     * sent, by whom, and (optionally) the provider's transaction reference.
     */
    public function complete(Withdrawal $withdrawal, User $admin, string $externalReference = ''): void
    {
        DB::transaction(function () use ($withdrawal, $admin, $externalReference) {
            $locked = Withdrawal::whereKey($withdrawal->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === WithdrawalStatus::Pending, 422, 'Only a pending withdrawal can be paid.');

            $locked->update([
                'status'             => WithdrawalStatus::Completed,
                'completed_by'       => $admin->id,
                'processed_at'       => now(),
                'external_reference' => $externalReference,
            ]);
            $this->audit->log('withdrawal.completed', $locked, [], ['external_ref' => $externalReference]);
        });

        $withdrawal->refresh();
    }
}
