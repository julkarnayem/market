<?php
namespace App\Services;

use App\Enums\TransactionType;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * All financial operations MUST go through this service.
 * Every operation uses row-level locking to prevent race conditions.
 * The ledger (wallet_transactions) is the source of truth — never update
 * wallet balances directly without creating a corresponding transaction.
 */
class WalletService
{
    /**
     * Credit the PENDING balance (seller earning lock).
     * Called immediately when an order completes.
     */
    public function creditPending(User $user, int $poisha, TransactionType $type, ?Model $reference = null, string $description = ''): WalletTransaction
    {
        abort_if($poisha <= 0, 422, 'Credit amount must be positive.');

        return DB::transaction(function () use ($user, $poisha, $type, $reference, $description) {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();

            $wallet->increment('pending_balance', $poisha);

            return $this->record($wallet, $type, $poisha, $reference, $description);
        });
    }

    /**
     * Move PENDING → AVAILABLE (seller earning release after 8h lock).
     * Returns false if already released (idempotency).
     */
    public function releasePending(User $user, int $poisha, ?Model $reference = null, string $description = ''): ?WalletTransaction
    {
        return DB::transaction(function () use ($user, $poisha, $reference, $description) {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();

            // Safety: never release more than is pending
            $safeAmount = min($poisha, $wallet->pending_balance);
            if ($safeAmount <= 0) return null;

            $wallet->decrement('pending_balance', $safeAmount);
            $wallet->increment('available_balance', $safeAmount);

            return $this->record($wallet, TransactionType::SellerEarningReleased, $safeAmount, $reference, $description);
        });
    }

    /**
     * Reverse a PENDING hold without paying it out — the seller's side of a
     * refund. The counterpart to creditPending(): the earning was credited to
     * pending when the order was paid, so a refund has to take it back out of
     * pending rather than debit the seller's available balance, which they may
     * already have withdrawn from.
     *
     * Clamped to what is actually held, so a partial reversal followed by a full
     * one cannot drive pending_balance negative. Returns null when there is
     * nothing left to reverse, which makes a replayed refund a no-op.
     */
    public function debitPending(User $user, int $poisha, ?Model $reference = null, string $description = ''): ?WalletTransaction
    {
        abort_if($poisha <= 0, 422, 'Reversal amount must be positive.');

        return DB::transaction(function () use ($user, $poisha, $reference, $description) {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();

            $safeAmount = min($poisha, $wallet->pending_balance);
            if ($safeAmount <= 0) return null;

            $wallet->decrement('pending_balance', $safeAmount);

            return $this->record($wallet, TransactionType::SellerEarningReversed, -$safeAmount, $reference, $description);
        });
    }

    /**
     * Debit AVAILABLE balance (withdrawal reserve, fee payment, etc.)
     * Throws if insufficient balance.
     */
    public function debitAvailable(User $user, int $poisha, TransactionType $type, ?Model $reference = null, string $description = ''): WalletTransaction
    {
        abort_if($poisha <= 0, 422, 'Debit amount must be positive.');

        return DB::transaction(function () use ($user, $poisha, $type, $reference, $description) {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();

            abort_if($wallet->available_balance < $poisha, 422, 'Insufficient available balance.');

            $wallet->decrement('available_balance', $poisha);

            return $this->record($wallet, $type, -$poisha, $reference, $description);
        });
    }

    /**
     * Credit AVAILABLE balance (refund to buyer, withdrawal rejection return).
     */
    public function creditAvailable(User $user, int $poisha, TransactionType $type, ?Model $reference = null, string $description = ''): WalletTransaction
    {
        abort_if($poisha <= 0, 422, 'Credit amount must be positive.');

        return DB::transaction(function () use ($user, $poisha, $type, $reference, $description) {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            $wallet->increment('available_balance', $poisha);
            return $this->record($wallet, $type, $poisha, $reference, $description);
        });
    }

    /**
     * Admin manual adjustment (requires explicit reason + audit).
     */
    public function adminAdjust(User $user, int $signedPoisha, string $reason, User $admin): WalletTransaction
    {
        return DB::transaction(function () use ($user, $signedPoisha, $reason, $admin) {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();

            if ($signedPoisha > 0) {
                $wallet->increment('available_balance', $signedPoisha);
            } else {
                $abs = abs($signedPoisha);
                abort_if($wallet->available_balance < $abs, 422, 'Insufficient balance for adjustment.');
                $wallet->decrement('available_balance', $abs);
            }

            return $this->record($wallet, TransactionType::AdminAdjustment, $signedPoisha, null,
                "Admin adjustment by {$admin->name}: {$reason}");
        });
    }

    private function record(Wallet $wallet, TransactionType $type, int $signedAmount, ?Model $reference, string $description): WalletTransaction
    {
        return WalletTransaction::create([
            'wallet_id'      => $wallet->id,
            'user_id'        => $wallet->user_id,
            'type'           => $type->value,
            'amount'         => $signedAmount,
            'available_after'=> $wallet->fresh()->available_balance,
            'pending_after'  => $wallet->fresh()->pending_balance,
            'reference_type' => $reference ? $reference->getMorphClass() : null,
            'reference_id'   => $reference?->getKey(),
            'description'    => $description,
        ]);
    }
}
