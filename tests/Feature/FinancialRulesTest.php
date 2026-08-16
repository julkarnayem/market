<?php
namespace Tests\Feature;

use App\Services\FeeCalculator;
use App\Services\SettingsService;
use App\Services\WalletService;
use App\Enums\TransactionType;
use App\Models\User;
use App\Models\Wallet;
use Tests\TestCase;

class FinancialRulesTest extends TestCase
{
    public function test_ten_percent_fee_no_free_threshold(): void
    {
        $calc = app(FeeCalculator::class);

        // ৳400 price — fee still applies (no ৳500 threshold)
        $snap = $calc->forOrder(40000, 1); // ৳400
        $this->assertSame(4000, $snap['seller_fee_amount']); // ৳40
        $this->assertSame(36000, $snap['seller_earning']);   // ৳360

        // ৳1 price
        $snap = $calc->forOrder(100, 1);
        $this->assertSame(10, $snap['seller_fee_amount']); // 10% of ৳1
    }

    public function test_buyer_fee_disabled_by_default(): void
    {
        $settings = app(SettingsService::class);
        $this->assertFalse($settings->buyerFeeEnabled());

        $calc = app(FeeCalculator::class);
        $snap = $calc->forOrder(100000, 1);
        $this->assertFalse($snap['buyer_fee_enabled']);
        $this->assertSame(0, $snap['buyer_fee_amount']);
        $this->assertSame(100000, $snap['buyer_total']); // no buyer fee added
    }

    public function test_wallet_debit_prevents_negative_balance(): void
    {
        $user   = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create(['available_balance' => 1000]);
        $svc    = app(WalletService::class);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $svc->debitAvailable($user, 2000, TransactionType::WithdrawalReserve, null, 'test');
    }

    public function test_wallet_credit_creates_ledger_entry(): void
    {
        $user   = User::factory()->create();
        $wallet = Wallet::factory()->for($user)->create();
        $svc    = app(WalletService::class);

        $svc->creditAvailable($user, 10000, TransactionType::AdminAdjustment, null, 'test credit');

        $wallet->refresh();
        $this->assertSame(10000, $wallet->available_balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $user->id,
            'amount'  => 10000,
        ]);
    }

    public function test_min_withdrawal_and_fee_constants(): void
    {
        $settings = app(SettingsService::class);
        $this->assertSame(5000, $settings->minWithdrawal()); // ৳50
        $this->assertSame(500,  $settings->withdrawalFee()); // ৳5
        $this->assertSame(72,   $settings->buyerProtectionHours());
        $this->assertSame(8,    $settings->earningLockHours());
        $this->assertSame(8,    $settings->offerValidityHours());
    }
}
