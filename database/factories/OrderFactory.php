<?php
namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrderFactory extends Factory
{
    public function definition(): array
    {
        $unitPrice = fake()->numberBetween(5000, 500000);
        $sellerFee = (int) round($unitPrice * 0.1);
        $sellerEarning = $unitPrice - $sellerFee;
        return [
            'reference'          => 'REF-' . strtoupper(Str::random(12)),
            'order_number'       => 'ORD-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6)),
            'buyer_user_id'      => User::factory(),
            'seller_user_id'     => User::factory(),
            'asset_id'           => Asset::factory(),
            'quantity'           => 1,
            'unit_price'         => $unitPrice,
            'subtotal'           => $unitPrice,
            'seller_fee_bp'      => 1000,
            'seller_fee_amount'  => $sellerFee,
            'buyer_fee_enabled'  => false,
            'buyer_fee_bp'       => 0,
            'buyer_fee_amount'   => 0,
            'platform_commission'=> $sellerFee,
            'buyer_total'        => $unitPrice,
            'seller_earning'     => $sellerEarning,
            'currency'           => 'BDT',
            'status'             => OrderStatus::DeliveryPending,
            'payment_status'     => 'paid',
            'delivery_status'    => 'not_started',
            'payment_gateway'    => 'uddoktapay',
            'earning_released'   => false,
        ];
    }

    public function pendingPayment(): static
    {
        return $this->state(fn () => [
            'status'         => OrderStatus::PendingPayment,
            'payment_status' => 'pending',
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status'           => OrderStatus::Completed,
            'payment_status'   => 'paid',
            'delivery_status'  => 'confirmed',
            'completed_at'     => now(),
            'seller_earning_available_at' => now()->subHours(10),
        ]);
    }
}
