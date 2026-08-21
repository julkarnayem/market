<?php
namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Withdrawal;
use App\Models\WithdrawalMethod;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Admin management of the payout-method set (Admin → Settings).
 *
 * The set is DB-driven now (withdrawal_methods table, seeded with the five
 * historical methods in its create migration). These lock in the two guarantees
 * that make it safe to hand the set to an admin:
 *   - what the admin switches off disappears from the user withdrawal form AND is
 *     rejected by validation — not merely hidden;
 *   - a method history depends on can't be deleted, and the form can never be
 *     emptied (the last active method is protected).
 */
class WithdrawalMethodAdminTest extends TestCase
{
    /** Staff holding the seeded admin role, which Gate::before grants fully. */
    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'admin')->value('id'));

        return $user->fresh();
    }

    private function keyId(string $key): int
    {
        return (int) WithdrawalMethod::query()->where('key', $key)->value('id');
    }

    // ── Adding ───────────────────────────────────────────────────────

    public function test_an_admin_can_add_a_method_and_it_appears_on_the_user_form(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.settings.methods.store'), [
                'label' => 'MyCash',
                'key'   => 'mycash',
                'type'  => 'mfs',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $method = WithdrawalMethod::query()->firstWhere('key', 'mycash');
        $this->assertNotNull($method);
        $this->assertTrue($method->is_active);
        $this->assertSame('mfs', $method->type);
        // Appended after the seeded five, not slotted in front of them.
        $this->assertGreaterThan(5, $method->sort_order);

        // A user picking a payout method now sees it.
        $this->actingAs(User::factory()->create())
            ->get('/dashboard/withdrawals')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('methods', fn ($methods) => collect($methods)->pluck('value')->contains('mycash'))
                ->etc()
            );
    }

    public function test_the_key_must_be_a_slug_and_unique(): void
    {
        $admin = $this->admin();

        // Uppercase / spaces are not a slug.
        $this->actingAs($admin)
            ->post(route('admin.settings.methods.store'), ['label' => 'Bad', 'key' => 'My Cash', 'type' => 'mfs'])
            ->assertSessionHasErrors('key');

        // bkash is already seeded.
        $this->actingAs($admin)
            ->post(route('admin.settings.methods.store'), ['label' => 'Another', 'key' => 'bkash', 'type' => 'mfs'])
            ->assertSessionHasErrors('key');

        $this->assertSame(5, WithdrawalMethod::count());
    }

    // ── Switching on / off ───────────────────────────────────────────

    public function test_switching_a_method_off_hides_it_from_the_user_form_and_rejects_it(): void
    {
        $this->actingAs($this->admin())
            ->patch(route('admin.settings.methods.update', $this->keyId('bkash')), ['is_active' => false])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertFalse(WithdrawalMethod::query()->firstWhere('key', 'bkash')->is_active);

        $user = User::factory()->create();

        // Gone from the dropdown…
        $this->actingAs($user)
            ->get('/dashboard/withdrawals')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('methods', fn ($methods) => collect($methods)->pluck('value')->doesntContain('bkash'))
                ->etc()
            );

        // …and a forced post is rejected, not just hidden.
        \App\Models\Wallet::create([
            'user_id' => $user->id, 'available_balance' => 500000, 'pending_balance' => 0, 'currency' => 'BDT',
        ]);
        $this->actingAs($user)
            ->post('/dashboard/withdrawals', ['amount_bdt' => 1000, 'method' => 'bkash', 'mfs_number' => '01712345678'])
            ->assertSessionHasErrors('method');

        $this->assertSame(0, Withdrawal::count());
    }

    public function test_switching_a_method_back_on_restores_it(): void
    {
        $admin = $this->admin();
        $id    = $this->keyId('bkash');

        $this->actingAs($admin)->patch(route('admin.settings.methods.update', $id), ['is_active' => false]);
        $this->actingAs($admin)->patch(route('admin.settings.methods.update', $id), ['is_active' => true])
            ->assertSessionHas('success');

        $this->assertTrue(WithdrawalMethod::query()->firstWhere('key', 'bkash')->is_active);

        $this->actingAs(User::factory()->create())
            ->get('/dashboard/withdrawals')
            ->assertInertia(fn (Assert $page) => $page
                ->where('methods', fn ($methods) => collect($methods)->pluck('value')->contains('bkash'))
                ->etc()
            );
    }

    public function test_the_last_active_method_cannot_be_switched_off(): void
    {
        // Leave bkash the only active one.
        WithdrawalMethod::query()->where('key', '!=', 'bkash')->update(['is_active' => false]);

        $this->actingAs($this->admin())
            ->patch(route('admin.settings.methods.update', $this->keyId('bkash')), ['is_active' => false])
            ->assertSessionHas('error');

        $this->assertTrue(WithdrawalMethod::query()->firstWhere('key', 'bkash')->is_active);
    }

    // ── Deleting ─────────────────────────────────────────────────────

    public function test_a_method_used_by_a_withdrawal_cannot_be_deleted(): void
    {
        // A withdrawal that used bkash. Built directly, not via a factory — the
        // Withdrawal model imports HasFactory without applying it, so
        // Withdrawal::factory() is unavailable (see BuildsMarketplace).
        $user = User::factory()->create();
        Withdrawal::create([
            'user_id'      => $user->id,
            'amount'       => 100000,
            'fee'          => 500,
            'net_amount'   => 99500,
            'currency'     => 'BDT',
            'method'       => 'mfs',
            'mfs_provider' => 'bkash',
            'method_key'   => 'bkash',
            'mfs_number'   => '01711111111',
            'status'       => \App\Enums\WithdrawalStatus::Pending,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('admin.settings.methods.destroy', $this->keyId('bkash')))
            ->assertSessionHas('error');

        $this->assertNotNull(WithdrawalMethod::query()->firstWhere('key', 'bkash'));
    }

    public function test_an_unused_method_can_be_deleted(): void
    {
        // upay is seeded but unused, and four other methods stay active.
        $this->actingAs($this->admin())
            ->delete(route('admin.settings.methods.destroy', $this->keyId('upay')))
            ->assertSessionHas('success');

        $this->assertNull(WithdrawalMethod::query()->firstWhere('key', 'upay'));
    }

    public function test_the_last_active_method_cannot_be_deleted(): void
    {
        // bkash unused (deletable on that count) but the only active one.
        WithdrawalMethod::query()->where('key', '!=', 'bkash')->update(['is_active' => false]);

        $this->actingAs($this->admin())
            ->delete(route('admin.settings.methods.destroy', $this->keyId('bkash')))
            ->assertSessionHas('error');

        $this->assertNotNull(WithdrawalMethod::query()->firstWhere('key', 'bkash'));
    }

    // ── Authorization ────────────────────────────────────────────────

    public function test_managing_methods_requires_the_settings_manage_permission(): void
    {
        // A moderator is an admin (passes the admin middleware) but lacks
        // settings.manage, so the controller's authorize() refuses.
        $moderator = User::factory()->create();
        $moderator->roles()->attach(Role::where('name', 'moderator')->value('id'));
        $moderator = $moderator->fresh();
        $this->assertTrue($moderator->isAdmin());
        $this->assertFalse($moderator->hasPermission('settings.manage'));

        $this->actingAs($moderator)
            ->post(route('admin.settings.methods.store'), ['label' => 'X', 'key' => 'x', 'type' => 'mfs'])
            ->assertForbidden();
        $this->actingAs($moderator)
            ->patch(route('admin.settings.methods.update', $this->keyId('bkash')), ['is_active' => false])
            ->assertForbidden();
        $this->actingAs($moderator)
            ->delete(route('admin.settings.methods.destroy', $this->keyId('upay')))
            ->assertForbidden();

        $this->assertSame(5, WithdrawalMethod::count());
        $this->assertTrue(WithdrawalMethod::query()->firstWhere('key', 'bkash')->is_active);
    }
}
