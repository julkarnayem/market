<?php
namespace Tests\Feature;

use App\Enums\TransactionType;
use App\Enums\WithdrawalStatus;
use App\Models\Role;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Models\Withdrawal;
use App\Models\WithdrawalMethod;
use App\Services\WalletService;
use App\Services\WithdrawalService;
use App\Support\Money;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\BuildsMarketplace;
use Tests\TestCase;

/**
 * The buyer/seller payout flow, end to end.
 *
 * The financial rule under test: the GROSS leaves the wallet on request and the
 * fee comes out of it, so ৳1,000 requested with a ৳20 fee debits ৳1,000 and pays
 * ৳980 — never ৳1,020. A rejection or cancellation returns the same ৳1,000, once.
 *
 * WithdrawalRulesTest already covers the minimum/fee/insufficient rules at the
 * service level, but every one of its four tests is a pinned baseline failure
 * (Wallet::factory() does not exist), so nothing there actually runs. These build
 * wallets the way the rest of the suite does instead.
 */
class WithdrawalFlowTest extends TestCase
{
    use BuildsMarketplace;

    /** A user with a funded available balance. */
    private function funded(int $availableBdt, int $pendingBdt = 0): User
    {
        $user = User::factory()->create();
        Wallet::create([
            'user_id'           => $user->id,
            'available_balance' => Money::toPoisha($availableBdt),
            'pending_balance'   => Money::toPoisha($pendingBdt),
            'currency'          => 'BDT',
        ]);

        return $user;
    }

    /** Staff holding the seeded admin role, which Gate::before grants fully. */
    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'admin')->value('id'));

        return $user->fresh();
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'amount_bdt' => 1000,
            'method'     => 'bkash',
            'mfs_number' => '01712345678',
        ], $overrides);
    }

    private function available(User $user): int
    {
        return (int) Wallet::where('user_id', $user->id)->value('available_balance');
    }

    private function fee(): int
    {
        return app(\App\Services\SettingsService::class)->withdrawalFee();
    }

    // ── Requesting ───────────────────────────────────────────────────

    public function test_a_buyer_can_request_a_withdrawal_from_available_balance(): void
    {
        $buyer = $this->funded(5000);

        $this->actingAs($buyer)
            ->post('/dashboard/withdrawals', $this->payload())
            ->assertRedirect(route('dashboard.withdrawals'))
            ->assertSessionHas('success');

        $w = Withdrawal::firstOrFail();
        $this->assertSame($buyer->id, (int) $w->user_id);
        $this->assertSame(WithdrawalStatus::Pending, $w->status);
        $this->assertSame(100000, $w->amount);                    // ৳1,000 gross
        $this->assertSame($this->fee(), $w->fee);
        $this->assertSame(100000 - $this->fee(), $w->net_amount); // fee out of it
        $this->assertSame('mfs', $w->method);
        $this->assertSame('bkash', $w->mfs_provider);

        // Gross reserved — not gross + fee.
        $this->assertSame(Money::toPoisha(5000) - 100000, $this->available($buyer));
    }

    public function test_the_reference_is_wd_id_plus_a_stored_random_token(): void
    {
        $user = $this->funded(5000);
        $a    = $this->pendingWithdrawal($user);
        $b    = $this->pendingWithdrawal($user->fresh());

        // A token is minted and stored on create…
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{10}$/', (string) $a->reference_token);
        // …and the handle is WD-{id}{TOKEN}, e.g. WD-7RASRSC42JFW.
        $this->assertSame('WD-' . $a->id . $a->reference_token, $a->reference());
        $this->assertMatchesRegularExpression('/^WD-\d+[A-Z0-9]{10}$/', $a->reference());

        // Stored, so it is stable across reloads — not recomputed each time.
        $this->assertSame($a->reference(), $a->fresh()->reference());
        // Two withdrawals get different random halves.
        $this->assertNotSame($a->reference_token, $b->reference_token);
    }

    public function test_a_seller_can_request_a_withdrawal_from_available_balance(): void
    {
        // A real seller balance: earning credited to pending at payment, then
        // released the way EarningReleaseService does it.
        $seller = $this->seller();
        app(WalletService::class)->creditPending(
            $seller, Money::toPoisha(3000), TransactionType::SellerEarningPending, null, 'earning',
        );
        app(WalletService::class)->releasePending($seller, Money::toPoisha(3000), null, 'released');

        $this->actingAs($seller)
            ->post('/dashboard/withdrawals', $this->payload(['amount_bdt' => 2000]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(1, Withdrawal::where('user_id', $seller->id)->count());
        $this->assertSame(Money::toPoisha(1000), $this->available($seller));
    }

    public function test_a_bank_withdrawal_records_its_account_details(): void
    {
        $user = $this->funded(5000);

        $this->actingAs($user)
            ->post('/dashboard/withdrawals', [
                'amount_bdt'          => 1000,
                'method'              => 'bank',
                'bank_account_name'   => 'Bilkis Buyer',
                'bank_account_number' => '1234567890123',
                'bank_name'           => 'Dutch-Bangla Bank',
                'bank_branch'         => 'Gulshan',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $w = Withdrawal::firstOrFail();
        $this->assertSame('bank', $w->method);
        $this->assertNull($w->mfs_provider);
        $this->assertSame('Dutch-Bangla Bank', $w->bank_name);
        // Rendered in full: only the owner and staff ever see a withdrawal.
        $this->assertStringContainsString('1234567890123', $w->fullAccount());
        $this->assertStringContainsString('Dutch-Bangla Bank', $w->fullAccount());
    }

    public function test_a_bank_withdrawal_requires_its_own_fields(): void
    {
        $user = $this->funded(5000);

        $this->actingAs($user)
            ->post('/dashboard/withdrawals', ['amount_bdt' => 1000, 'method' => 'bank'])
            ->assertSessionHasErrors(['bank_account_name', 'bank_account_number', 'bank_name']);

        // …and mobile money still requires a number.
        $this->actingAs($user)
            ->post('/dashboard/withdrawals', ['amount_bdt' => 1000, 'method' => 'bkash'])
            ->assertSessionHasErrors('mfs_number');

        $this->assertSame(0, Withdrawal::count());
    }

    public function test_more_than_the_available_balance_is_refused(): void
    {
        $user = $this->funded(5000);

        $this->actingAs($user)
            ->post('/dashboard/withdrawals', $this->payload(['amount_bdt' => 6000]))
            ->assertStatus(422);

        $this->assertSame(0, Withdrawal::count());
        $this->assertSame(Money::toPoisha(5000), $this->available($user));
    }

    public function test_zero_and_negative_amounts_are_refused(): void
    {
        $user = $this->funded(5000);

        foreach ([0, -1, -1000] as $bad) {
            $this->actingAs($user)
                ->post('/dashboard/withdrawals', $this->payload(['amount_bdt' => $bad]))
                ->assertSessionHasErrors('amount_bdt');
        }

        $this->assertSame(0, Withdrawal::count());
    }

    public function test_the_minimum_withdrawal_is_enforced(): void
    {
        $user   = $this->funded(5000);
        $minBdt = Money::toBdt(app(\App\Services\SettingsService::class)->minWithdrawal());

        $this->actingAs($user)
            ->post('/dashboard/withdrawals', $this->payload(['amount_bdt' => max(0.01, $minBdt - 1)]))
            ->assertSessionHasErrors('amount_bdt');

        $this->assertSame(0, Withdrawal::count());
    }

    /** Pending balance is not spendable — only `available_balance` is. */
    public function test_pending_balance_cannot_be_withdrawn(): void
    {
        $user = $this->funded(availableBdt: 0, pendingBdt: 5000);

        $this->actingAs($user)
            ->post('/dashboard/withdrawals', $this->payload())
            ->assertStatus(422);

        $this->assertSame(0, Withdrawal::count());
        // The hold is untouched.
        $this->assertSame(Money::toPoisha(5000), (int) Wallet::where('user_id', $user->id)->value('pending_balance'));
    }

    /**
     * A seller's earning sits in pending until it is released, so an unsettled
     * order's money is not withdrawable — the escrow case.
     */
    public function test_an_unreleased_seller_earning_cannot_be_withdrawn(): void
    {
        $seller = $this->seller();
        app(WalletService::class)->creditPending(
            $seller, Money::toPoisha(4000), TransactionType::SellerEarningPending, null, 'held earning',
        );

        $this->actingAs($seller)
            ->post('/dashboard/withdrawals', $this->payload())
            ->assertStatus(422);

        $this->assertSame(0, Withdrawal::count());
        $this->assertSame(0, $this->available($seller));
    }

    /** The balance is re-read under a row lock, so it cannot be overdrawn twice. */
    public function test_two_withdrawals_cannot_overdraw_the_wallet(): void
    {
        $user    = $this->funded(1000);
        $service = app(WithdrawalService::class);

        $service->request($user, Money::toPoisha(800), WithdrawalMethod::query()->firstWhere('key', 'bkash'), ['mfs_number' => '01712345678']);

        // The second ৳800 has only ৳200 left behind it.
        try {
            $service->request($user->fresh(), Money::toPoisha(800), WithdrawalMethod::query()->firstWhere('key', 'bkash'), ['mfs_number' => '01712345678']);
            $this->fail('The second withdrawal overdrew the wallet.');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertSame(1, Withdrawal::count());
        $this->assertSame(Money::toPoisha(200), $this->available($user));
        $this->assertGreaterThanOrEqual(0, $this->available($user));
    }

    public function test_a_withdrawal_writes_a_reserve_ledger_entry(): void
    {
        $user = $this->funded(5000);

        $this->actingAs($user)->post('/dashboard/withdrawals', $this->payload());

        $w  = Withdrawal::firstOrFail();
        $tx = WalletTransaction::findOrFail($w->wallet_transaction_id);
        $this->assertSame(TransactionType::WithdrawalReserve->value, $tx->type->value);
        $this->assertSame(100000, (int) abs($tx->amount));
    }

    public function test_a_double_submitted_form_creates_one_withdrawal(): void
    {
        $user    = $this->funded(5000);
        $payload = $this->payload(['client_request_id' => 'form-abc-123']);

        $this->actingAs($user)->post('/dashboard/withdrawals', $payload)->assertRedirect();
        $this->actingAs($user)->post('/dashboard/withdrawals', $payload)->assertRedirect();

        $this->assertSame(1, Withdrawal::count());
        // Reserved once, not twice.
        $this->assertSame(Money::toPoisha(5000) - 100000, $this->available($user));
    }

    /** A forged reserve amount in the payload has nothing to bind to. */
    public function test_the_amount_cannot_be_manipulated_through_the_payload(): void
    {
        $user = $this->funded(5000);

        $this->actingAs($user)
            ->post('/dashboard/withdrawals', $this->payload([
                'amount'     => 999999999,
                'fee'        => 0,
                'net_amount' => 999999999,
                'user_id'    => $this->admin()->id,
                'status'     => 'approved',
            ]))
            ->assertRedirect();

        $w = Withdrawal::firstOrFail();
        $this->assertSame($user->id, (int) $w->user_id);
        $this->assertSame(100000, $w->amount);
        $this->assertSame($this->fee(), $w->fee);
        $this->assertSame(WithdrawalStatus::Pending, $w->status);
    }

    // ── Admin pay / reject ───────────────────────────────────────────

    private function pendingWithdrawal(User $user): Withdrawal
    {
        return app(WithdrawalService::class)->request(
            $user, Money::toPoisha(1000), WithdrawalMethod::query()->firstWhere('key', 'bkash'), ['mfs_number' => '01712345678'],
        );
    }

    public function test_an_admin_can_mark_a_pending_withdrawal_paid(): void
    {
        $user   = $this->funded(5000);
        $w      = $this->pendingWithdrawal($user);
        $before = $this->available($user);

        $this->actingAs($this->admin())
            ->post("/admin/withdrawals/{$w->id}/complete", ['external_reference' => 'TRX-1'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $fresh = $w->fresh();
        $this->assertSame(WithdrawalStatus::Completed, $fresh->status);
        $this->assertNotNull($fresh->processed_at);
        $this->assertNotNull($fresh->completed_by);
        $this->assertSame('TRX-1', $fresh->external_reference);
        // Paying out moves no money now — the gross left at request time.
        $this->assertSame($before, $this->available($user));
    }

    /**
     * The destination is shown in full everywhere it appears — the owner's own
     * history and both admin surfaces (the list they act from and the detail
     * page). Only those two parties ever see a withdrawal, and staff cannot send
     * money to a masked number, so nothing is masked.
     */
    public function test_the_account_number_is_shown_in_full(): void
    {
        $user = $this->funded(5000);

        // A bank withdrawal, so there is a full account number to check.
        $this->actingAs($user)->post('/dashboard/withdrawals', [
            'amount_bdt'          => 1000,
            'method'              => 'bank',
            'bank_account_name'   => 'Bilkis Buyer',
            'bank_account_number' => '1234567890123',
            'bank_name'           => 'Dutch-Bangla Bank',
            'bank_branch'         => 'Gulshan',
        ])->assertRedirect();

        $w     = Withdrawal::firstOrFail();
        $admin = $this->admin();

        // The list staff act from (Mark paid is inline here) shows it in full.
        $this->actingAs($admin)
            ->get('/admin/withdrawals')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Withdrawals/Index')
                ->where('withdrawals.data.0.account', fn ($v) => str_contains((string) $v, '1234567890123'))
                ->where('withdrawals.data.0.account', fn ($v) => ! str_contains((string) $v, '*'))
                ->etc()
            );

        // The detail page carries the full structured destination too.
        $this->actingAs($admin)
            ->get("/admin/withdrawals/{$w->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Withdrawals/Show')
                ->where('payout.method', 'bank')
                ->where('payout.bank_account_number', '1234567890123')
                ->where('payout.bank_account_name', 'Bilkis Buyer')
                ->where('payout.bank_name', 'Dutch-Bangla Bank')
                ->etc()
            );

        // The owner sees their own number in full — it is theirs.
        $this->actingAs($user)
            ->get('/dashboard/withdrawals')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Withdrawals')
                ->where('withdrawals.data.0.account', fn ($v) => str_contains((string) $v, '1234567890123'))
                ->where('withdrawals.data.0.account', fn ($v) => ! str_contains((string) $v, '*'))
                ->etc()
            );
    }

    public function test_an_admin_can_reject_and_the_funds_return_once(): void
    {
        $user = $this->funded(5000);
        $w    = $this->pendingWithdrawal($user);
        $this->assertSame(Money::toPoisha(4000), $this->available($user));

        $this->actingAs($this->admin())
            ->post("/admin/withdrawals/{$w->id}/reject", ['reason' => 'Account name mismatch.'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $fresh = $w->fresh();
        $this->assertSame(WithdrawalStatus::Rejected, $fresh->status);
        $this->assertSame('Account name mismatch.', $fresh->rejection_reason);
        $this->assertNotNull($fresh->rejected_at);

        // The full gross is back — exactly once.
        $this->assertSame(Money::toPoisha(5000), $this->available($user));
        $this->assertSame(1, WalletTransaction::where('type', TransactionType::WithdrawalReturn->value)->count());
    }

    public function test_rejecting_twice_refunds_only_once(): void
    {
        $user  = $this->funded(5000);
        $w     = $this->pendingWithdrawal($user);
        $admin = $this->admin();

        $this->actingAs($admin)->post("/admin/withdrawals/{$w->id}/reject", ['reason' => 'First.'])->assertRedirect();
        $this->actingAs($admin)->post("/admin/withdrawals/{$w->id}/reject", ['reason' => 'Second.'])->assertStatus(422);

        // Not ৳6,000 — the balance was restored a single time.
        $this->assertSame(Money::toPoisha(5000), $this->available($user));
        $this->assertSame(1, WalletTransaction::where('type', TransactionType::WithdrawalReturn->value)->count());
        $this->assertSame('First.', $w->fresh()->rejection_reason);
    }

    public function test_a_rejection_requires_a_reason(): void
    {
        $user = $this->funded(5000);
        $w    = $this->pendingWithdrawal($user);

        $this->actingAs($this->admin())
            ->post("/admin/withdrawals/{$w->id}/reject", [])
            ->assertSessionHasErrors('reason');

        $this->assertSame(WithdrawalStatus::Pending, $w->fresh()->status);
    }

    public function test_a_completed_withdrawal_cannot_be_rejected(): void
    {
        $user  = $this->funded(5000);
        $w     = $this->pendingWithdrawal($user);
        $admin = $this->admin();

        $this->actingAs($admin)->post("/admin/withdrawals/{$w->id}/complete", ['external_reference' => 'TRX-1'])->assertRedirect();
        $this->assertSame(WithdrawalStatus::Completed, $w->fresh()->status);

        $this->actingAs($admin)
            ->post("/admin/withdrawals/{$w->id}/reject", ['reason' => 'Too late.'])
            ->assertStatus(422);

        $this->assertSame(WithdrawalStatus::Completed, $w->fresh()->status);
        // No money came back for an already-paid payout.
        $this->assertSame(Money::toPoisha(4000), $this->available($user));
    }

    public function test_completing_twice_does_not_double_pay(): void
    {
        $user  = $this->funded(5000);
        $w     = $this->pendingWithdrawal($user);
        $admin = $this->admin();

        $this->actingAs($admin)->post("/admin/withdrawals/{$w->id}/complete", ['external_reference' => 'TRX-1'])->assertRedirect();
        $processedAt = $w->fresh()->processed_at;

        $this->actingAs($admin)->post("/admin/withdrawals/{$w->id}/complete", ['external_reference' => 'TRX-2'])->assertStatus(422);

        $this->assertEquals($processedAt, $w->fresh()->processed_at);
        $this->assertSame('TRX-1', $w->fresh()->external_reference);
    }

    // ── User cancellation ────────────────────────────────────────────

    public function test_a_user_can_cancel_their_own_pending_withdrawal(): void
    {
        $user = $this->funded(5000);
        $w    = $this->pendingWithdrawal($user);

        $this->actingAs($user)
            ->post("/dashboard/withdrawals/{$w->id}/cancel")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(WithdrawalStatus::Cancelled, $w->fresh()->status);
        $this->assertNotNull($w->fresh()->cancelled_at);
        $this->assertSame(Money::toPoisha(5000), $this->available($user));
        $this->assertSame(1, WalletTransaction::where('type', TransactionType::WithdrawalReturn->value)->count());
    }

    public function test_cancelling_twice_refunds_only_once(): void
    {
        $user = $this->funded(5000);
        $w    = $this->pendingWithdrawal($user);

        $this->actingAs($user)->post("/dashboard/withdrawals/{$w->id}/cancel")->assertRedirect();
        $this->actingAs($user)->post("/dashboard/withdrawals/{$w->id}/cancel")->assertStatus(422);

        $this->assertSame(Money::toPoisha(5000), $this->available($user));
        $this->assertSame(1, WalletTransaction::where('type', TransactionType::WithdrawalReturn->value)->count());
    }

    public function test_a_paid_withdrawal_cannot_be_cancelled_by_the_user(): void
    {
        $user = $this->funded(5000);
        $w    = $this->pendingWithdrawal($user);

        $this->actingAs($this->admin())
            ->post("/admin/withdrawals/{$w->id}/complete", ['external_reference' => 'TRX-1'])
            ->assertRedirect();

        $this->actingAs($user)
            ->post("/dashboard/withdrawals/{$w->id}/cancel")
            ->assertStatus(422);

        $this->assertSame(WithdrawalStatus::Completed, $w->fresh()->status);
        // Money stays out — it was paid, not returned.
        $this->assertSame(Money::toPoisha(4000), $this->available($user));
    }

    public function test_a_user_cannot_cancel_someone_elses_withdrawal(): void
    {
        $owner    = $this->funded(5000);
        $stranger = $this->funded(5000);
        $w        = $this->pendingWithdrawal($owner);

        $this->actingAs($stranger)
            ->post("/dashboard/withdrawals/{$w->id}/cancel")
            ->assertForbidden();

        $this->assertSame(WithdrawalStatus::Pending, $w->fresh()->status);
        $this->assertSame(Money::toPoisha(4000), $this->available($owner));
        $this->assertSame(Money::toPoisha(5000), $this->available($stranger));
    }

    // ── Authorization ────────────────────────────────────────────────

    public function test_a_normal_user_cannot_reach_admin_withdrawal_management(): void
    {
        $user = $this->funded(5000);
        $w    = $this->pendingWithdrawal($user);

        foreach ([
            ['get',  "/admin/withdrawals"],
            ['get',  "/admin/withdrawals/{$w->id}"],
            ['post', "/admin/withdrawals/{$w->id}/reject"],
            ['post', "/admin/withdrawals/{$w->id}/complete"],
        ] as [$verb, $url]) {
            $this->actingAs($user)->{$verb}($url)->assertForbidden();
        }

        $this->assertSame(WithdrawalStatus::Pending, $w->fresh()->status);
    }

    /** A seller is just as much a normal user on the admin side. */
    public function test_a_seller_cannot_process_another_users_withdrawal(): void
    {
        $owner  = $this->funded(5000);
        $w      = $this->pendingWithdrawal($owner);
        $seller = $this->seller();

        $this->actingAs($seller)->post("/admin/withdrawals/{$w->id}/complete")->assertForbidden();
        $this->actingAs($seller)->post("/admin/withdrawals/{$w->id}/reject", ['reason' => 'x'])->assertForbidden();

        $this->assertSame(WithdrawalStatus::Pending, $w->fresh()->status);
        $this->assertSame(Money::toPoisha(4000), $this->available($owner));
    }

    /** The history list is scoped to the viewer — no IDOR across users. */
    public function test_a_user_only_sees_their_own_withdrawal_history(): void
    {
        $mine   = $this->funded(5000);
        $theirs = $this->funded(5000);
        $this->pendingWithdrawal($mine);
        $this->pendingWithdrawal($theirs);

        $this->actingAs($mine)
            ->get('/dashboard/withdrawals')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Withdrawals')
                ->has('withdrawals.data', 1)
                ->where('withdrawals.data.0.reference', Withdrawal::where('user_id', $mine->id)->first()->reference())
                ->has('methods', WithdrawalMethod::query()->active()->count())
                ->etc()
            );
    }

    /** Staff without the payout permissions are refused, so admin is not a blanket. */
    public function test_staff_without_the_permission_cannot_process_payouts(): void
    {
        $user = $this->funded(5000);
        $w    = $this->pendingWithdrawal($user);

        $moderator = User::factory()->create();
        $moderator->roles()->attach(Role::where('name', 'moderator')->value('id'));
        $moderator = $moderator->fresh();
        $this->assertTrue($moderator->isAdmin());
        $this->assertFalse($moderator->hasPermission('withdrawals.complete'));

        $this->actingAs($moderator)->post("/admin/withdrawals/{$w->id}/complete")->assertForbidden();
        $this->actingAs($moderator)->post("/admin/withdrawals/{$w->id}/reject", ['reason' => 'x'])->assertForbidden();

        $this->assertSame(WithdrawalStatus::Pending, $w->fresh()->status);
        $this->assertSame(Money::toPoisha(4000), $this->available($user));
    }

    public function test_the_page_reports_the_balances_and_total_withdrawn(): void
    {
        $user  = $this->funded(5000, pendingBdt: 1200);
        $w     = $this->pendingWithdrawal($user);
        $admin = $this->admin();

        $this->actingAs($admin)->post("/admin/withdrawals/{$w->id}/complete", ['external_reference' => 'TRX-9']);

        $this->actingAs($user)
            ->get('/dashboard/withdrawals')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('availableFormatted', Money::format(Money::toPoisha(4000)))
                ->where('pendingFormatted', Money::format(Money::toPoisha(1200)))
                ->where('totalWithdrawnFormatted', Money::format(Money::toPoisha(1000)))
                ->where('withdrawals.data.0.can_cancel', false)
                ->etc()
            );
    }
}
