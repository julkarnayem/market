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
     * The caller's wallet row, locked for the rest of the transaction — created
     * first if it does not exist yet.
     *
     * A wallet is an implicit account: every user has one conceptually, but rows
     * are only inserted at registration (RegisteredUserController), so any account
     * created another way — seeded, promoted, imported, or predating that code —
     * had none. Every lookup here used firstOrFail(), so a credit or debit for such
     * a user threw ModelNotFoundException *inside* its own DB::transaction: the
     * whole operation rolled back and the caller saw a 404 with no explanation.
     * That is what stopped dispute refunds from being processed — the settlement
     * itself was correct, it just could not reach the buyer's balance.
     *
     * Creating on demand is safe rather than lenient: wallets.user_id is UNIQUE, so
     * two concurrent callers cannot end up with two rows, the new row starts at
     * zero, and every balance rule still applies afterwards — debitAvailable()
     * still refuses to overdraw a fresh zero-balance wallet.
     */
    private function lockWallet(User $user): Wallet
    {
        Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['available_balance' => 0, 'pending_balance' => 0, 'currency' => 'BDT'],
        );

        // Re-read under a lock: firstOrCreate does not take one, and every caller
        // relies on this row being pinned for the rest of its transaction.
        return Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * Credit the PENDING balance (seller earning lock).
     * Called immediately when an order completes.
     */
    public function creditPending(User $user, int $poisha, TransactionType $type, ?Model $reference = null, string $description = ''): WalletTransaction
    {
        abort_if($poisha <= 0, 422, 'Credit amount must be positive.');

        return DB::transaction(function () use ($user, $poisha, $type, $reference, $description) {
            $wallet = $this->lockWallet($user);

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
            $wallet = $this->lockWallet($user);

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
            $wallet = $this->lockWallet($user);

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
            $wallet = $this->lockWallet($user);

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
            $wallet = $this->lockWallet($user);
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
            $wallet = $this->lockWallet($user);

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
