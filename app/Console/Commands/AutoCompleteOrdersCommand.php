<?php
namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Console\Command;

class AutoCompleteOrdersCommand extends Command
{
    protected $signature   = 'orders:auto-complete';
    protected $description = 'Auto-complete delivered orders whose 72-hour buyer protection window has elapsed.';

    public function handle(OrderService $service): void
    {
        $orders = Order::where('status', OrderStatus::Delivered)
            ->where('auto_complete_at', '<=', now())
            ->whereNull('auto_completed_at')
            ->get();

        $count = 0;
        foreach ($orders as $order) {
            try {
                $service->autoComplete($order);
                $count++;
            } catch (\Throwable $e) {
                $this->error("Order #{$order->id}: {$e->getMessage()}");
            }
        }
        $this->info("Auto-completed {$count} order(s).");
    }
}
