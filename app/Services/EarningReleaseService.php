<?php
namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\TransactionType;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class EarningReleaseService
{
    public function __construct(
        private readonly WalletService $wallet,
        private readonly AuditLogger   $audit,
    ) {}

    /**
     * Release seller earnings for all completed orders past their lock time.
     * Idempotent: `earning_released` flag prevents double-crediting.
     */
    public function releaseEligible(): int
    {
        $orders = Order::where('status', OrderStatus::Completed)
            ->where('earning_released', false)
            ->where('seller_earning_available_at', '<=', now())
            ->get();

        $count = 0;
        foreach ($orders as $order) {
            try {
                $this->releaseOne($order);
                $count++;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error("Earning release failed for order #{$order->id}: {$e->getMessage()}");
            }
        }
        return $count;
    }

    public function releaseOne(Order $order): void
    {
        DB::transaction(function () use ($order) {
            // Re-read locked
            $order = Order::where('id', $order->id)
                ->where('earning_released', false)
                ->lockForUpdate()
                ->first();

            if (!$order) return; // Already released (concurrent run)

            $this->wallet->releasePending($order->seller, $order->seller_earning, $order,
                "Seller earning released for order #{$order->order_number}");

            $order->update(['earning_released' => true]);
            $this->audit->log('order.earning_released', $order);
        });
    }
}
