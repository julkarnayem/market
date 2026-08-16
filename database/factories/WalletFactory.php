<?php
namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class WalletFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'           => User::factory(),
            'available_balance' => 0,
            'pending_balance'   => 0,
            'currency'          => 'BDT',
        ];
    }

    public function withBalance(int $poisha): static
    {
        return $this->state(fn () => ['available_balance' => $poisha]);
    }
}
