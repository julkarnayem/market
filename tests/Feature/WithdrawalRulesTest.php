<?php
namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Services\FeeCalculator;
use Tests\TestCase;

class WithdrawalRulesTest extends TestCase
{
    public function test_withdrawal_below_minimum_rejected(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->create(['user_id' => $user->id, 'available_balance' => 4900]); // ৳49

        $response = $this->actingAs($user)->post('/dashboard/withdrawals', [
            'amount_bdt'   => 49,
            'mfs_provider' => 'bkash',
            'mfs_number'   => '01711111111',
        ]);

        $response->assertSessionHasErrors();
        $this->assertDatabaseMissing('withdrawals', ['user_id' => $user->id]);
    }

    public function test_withdrawal_minimum_accepted(): void
    {
        $user = User::factory()->create();
        $wallet = Wallet::factory()->create(['user_id' => $user->id, 'available_balance' => 10000]); // ৳100

        $response = $this->actingAs($user)->post('/dashboard/withdrawals', [
            'amount_bdt'   => 50,
            'mfs_provider' => 'bkash',
            'mfs_number'   => '01711111111',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('withdrawals', ['user_id' => $user->id, 'amount' => 5000]);
    }

    public function test_withdrawal_fee_is_500_poisha(): void
    {
        $user = User::factory()->create();
        Wallet::factory()->create(['user_id' => $user->id, 'available_balance' => 10000]);

        $this->actingAs($user)->post('/dashboard/withdrawals', [
            'amount_bdt'   => 50,
            'mfs_provider' => 'bkash',
            'mfs_number'   => '01711111111',
        ]);

        $this->assertDatabaseHas('withdrawals', [
            'user_id'    => $user->id,
            'amount'     => 5000,   // ৳50 requested
            'fee'        => 500,    // ৳5 fee
            'net_amount' => 4500,   // ৳45 net
        ]);
    }

    public function test_withdrawal_insufficient_balance_rejected(): void
    {
        $user = User::factory()->create();
        Wallet::factory()->create(['user_id' => $user->id, 'available_balance' => 1000]); // ৳10

        $response = $this->actingAs($user)->post('/dashboard/withdrawals', [
            'amount_bdt'   => 50,
            'mfs_provider' => 'bkash',
            'mfs_number'   => '01711111111',
        ]);

        $response->assertSessionHasErrors();
        $this->assertDatabaseMissing('withdrawals', ['user_id' => $user->id]);
    }
}
