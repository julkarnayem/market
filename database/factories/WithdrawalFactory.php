<?php
namespace Database\Factories;

use App\Enums\WithdrawalStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class WithdrawalFactory extends Factory
{
    public function definition(): array
    {
        $gross = fake()->numberBetween(5000, 100000);
        $fee   = 500;
        return [
            'user_id'      => User::factory()->withWallet(),
            'amount'       => $gross,
            'fee'          => $fee,
            'net_amount'   => $gross - $fee,
            'currency'     => 'BDT',
            'method'       => 'mfs',
            'mfs_provider' => 'bkash',
            'method_key'   => 'bkash',
            'mfs_number'   => '01711111111',
            'status'       => WithdrawalStatus::Pending,
        ];
    }
}
