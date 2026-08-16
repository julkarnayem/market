<?php
namespace App\Services;

use App\Enums\DisputeStatus;
use App\Enums\OrderStatus;
use App\Enums\TransactionType;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class DisputeService
{
    public function __construct(
        private readonly WalletService $wallet,
        private readonly AuditLogger   $audit,
    ) {}

    /**
     * FULL REFUND — buyer receives the full buyer_total back.
     * Seller earning is NOT released.
     * Idempotent: checks resolution_type before processing.
     */
    public function resolveFullRefund(Dispute $dispute, User $admin, string $note = ''): void
    {
        abort_unless($dispute->isResolvable(), 422, 'Dispute cannot be resolved in its current state.');

        DB::transaction(function () use ($dispute, $admin, $note) {
            // Idempotency: re-read inside transaction
            $dispute = Dispute::where('id', $dispute->id)->lockForUpdate()->firstOrFail();
            abort_unless($dispute->isResolvable(), 422, 'Already resolved.');

            $order = $dispute->order;
            $refundAmount = $order->buyer_total;

            // Credit buyer wallet
            $this->wallet->creditAvailable($order->buyer, $refundAmount, TransactionType::Refund, $order,
                "Full refund for order #{$order->order_number}");

            // Mark order
            $order->update([
                'status'          => OrderStatus::Refunded,
                'earning_released'=> false, // ensure no seller release
            ]);

            $dispute->update([
                'status'             => DisputeStatus::Resolved,
                'resolution'         => 'full_refund',
                'resolution_type'    => 'full_refund',
                'resolution_amount'  => $refundAmount,
                'resolution_note'    => $note,
                'admin_notes'        => $note,
                'resolved_by'        => $admin->id,
                'resolved_at'        => now(),
            ]);

            $this->audit->log('dispute.full_refund', $dispute, [], ['amount' => $refundAmount]);
        });
    }

    /**
     * PARTIAL REFUND — buyer receives a partial amount.
     */
    public function resolvePartialRefund(Dispute $dispute, User $admin, int $refundPoisha, string $note = ''): void
    {
        abort_unless($dispute->isResolvable(), 422, 'Dispute cannot be resolved in its current state.');
        abort_if($refundPoisha <= 0, 422, 'Refund amount must be positive.');
        abort_if($refundPoisha > $dispute->maxRefundable(), 422, 'Refund cannot exceed order total: ' . Money::format($dispute->maxRefundable()));

        DB::transaction(function () use ($dispute, $admin, $refundPoisha, $note) {
            $dispute = Dispute::where('id', $dispute->id)->lockForUpdate()->firstOrFail();
            abort_unless($dispute->isResolvable(), 422, 'Already resolved.');

            $order = $dispute->order;
            $this->wallet->creditAvailable($order->buyer, $refundPoisha, TransactionType::PartialRefund, $order,
                "Partial refund for order #{$order->order_number}");

            // Calculate remaining seller earning after refund
            $refundRatio    = $refundPoisha / $order->buyer_total;
            $adjustedEarning = (int)($order->seller_earning * (1 - $refundRatio));

            $order->update(['status' => OrderStatus::PartiallyRefunded]);

            $dispute->update([
                'status'            => DisputeStatus::Resolved,
                'resolution'        => 'partial_refund',
                'resolution_type'   => 'partial_refund',
                'resolution_amount' => $refundPoisha,
                'resolution_note'   => $note,
                'admin_notes'       => $note,
                'resolved_by'       => $admin->id,
                'resolved_at'       => now(),
            ]);

            $this->audit->log('dispute.partial_refund', $dispute, [], ['amount' => $refundPoisha]);
        });
    }

    /**
     * SELLER PAYMENT RELEASE — seller gets their earning as if order completed normally.
     * Idempotent: will not release twice.
     */
    public function resolveSellerRelease(Dispute $dispute, User $admin, string $note = ''): void
    {
        abort_unless($dispute->isResolvable(), 422, 'Dispute cannot be resolved in its current state.');

        DB::transaction(function () use ($dispute, $admin, $note) {
            $dispute = Dispute::where('id', $dispute->id)->lockForUpdate()->firstOrFail();
            abort_unless($dispute->isResolvable(), 422, 'Already resolved.');

            $order = Order::where('id', $dispute->order_id)->lockForUpdate()->firstOrFail();
            abort_if($order->earning_released, 422, 'Seller earning was already released.');

            $this->wallet->creditAvailable($order->seller, $order->seller_earning, TransactionType::SellerEarningReleased, $order,
                "Dispute resolved — seller earning released for order #{$order->order_number}");

            $order->update([
                'status'           => OrderStatus::Completed,
                'earning_released' => true,
                'completed_at'     => now(),
                'seller_earning_available_at' => now(),
            ]);

            $dispute->update([
                'status'          => DisputeStatus::Resolved,
                'resolution'      => 'seller_payment_release',
                'resolution_type' => 'seller_payment_release',
                'resolved_by'     => $admin->id,
                'resolved_at'     => now(),
                'resolution_note' => $note,
            ]);

            $this->audit->log('dispute.seller_released', $dispute, [], ['earning' => $order->seller_earning]);
        });
    }

    public function updateStatus(Dispute $dispute, User $admin, string $status, string $note = ''): void
    {
        $dispute->update(['status' => $status, 'admin_notes' => $note]);
        $this->audit->log("dispute.status_changed.{$status}", $dispute);
    }
}
