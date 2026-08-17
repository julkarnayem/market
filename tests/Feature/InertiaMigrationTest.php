<?php
namespace Tests\Feature;

use App\Enums\TicketStatus;
use App\Models\Asset;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Conversation;
use App\Models\Dispute;
use App\Models\Favorite;
use App\Models\Order;
use App\Models\PhoneOtp;
use App\Models\Role;
use App\Models\SellerVerification;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Auth\Notifications\VerifyEmail as VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Covers the Blade -> Inertia/Vue migration.
 *
 * These assertions are the contract resources/js/Pages/Faq.vue and
 * resources/js/Layouts/PublicLayout.vue rely on, plus a guard that the
 * not-yet-migrated Blade pages keep working alongside Inertia.
 */
class InertiaMigrationTest extends TestCase
{
    public function test_faq_renders_the_inertia_page_with_its_props(): void
    {
        $this->get('/faq')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Faq')
                ->has('faqs', 5)
                ->has('faqs.0', fn (Assert $faq) => $faq
                    ->where('question', 'Is there a fee to list an asset?')
                    ->has('answer')
                )
            );
    }

    public function test_shared_props_are_present_for_a_guest(): void
    {
        $this->get('/faq')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user', null)
                ->has('appName')
                ->has('ziggy.routes')
            );
    }

    /** PublicLayout reads user.can_sell / is_admin to decide what to show. */
    public function test_shared_auth_user_is_a_whitelist_for_an_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/faq')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.id', $user->id)
                ->where('auth.user.email', $user->email)
                ->has('auth.user.can_sell')
                ->has('auth.user.is_admin')
                // Credentials must never reach the client.
                ->missing('auth.user.password')
                ->missing('auth.user.remember_token')
            );
    }

    /**
     * Coexistence guard: Blade and Inertia must keep working side by side.
     * Every user-facing dashboard page is Inertia now, so this points at the
     * still-Blade admin area (audit logs); repoint it as admin pages migrate.
     * /marketplace, /dashboard, /dashboard/favorites, /dashboard/wallet,
     * /dashboard/promotions, /dashboard/notifications, /dashboard/tickets and
     * /dashboard/messages have all already passed through here.
     */
    public function test_unmigrated_blade_pages_still_render(): void
    {
        $admin = User::factory()->create();
        $role  = Role::create([
            'name'          => 'ckpt22-admin-guard',
            'display_name'  => 'Administrator',
            'is_admin_role' => true,
        ]);
        $admin->roles()->attach($role->id);

        $this->actingAs($admin)
            ->get('/admin/audit-logs')
            ->assertOk()
            ->assertDontSee('data-page', false);
    }

    /**
     * Admin/Index.vue reads a whitelisted `stats` object plus recentOrders /
     * recentTickets. Requires an admin user (a Role with is_admin_role passes
     * the `admin` middleware); the seeded 'admin' role name is taken, so use a
     * test-unique name.
     */
    public function test_admin_dashboard_renders_the_platform_overview(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get('/admin')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Index')
                ->has('stats', fn (Assert $s) => $s
                    ->has('users')
                    ->has('active_users')
                    ->has('published_listings')
                    ->has('orders_month')
                    ->has('revenue_month_formatted')
                    ->has('active_promotions')
                    ->has('open_tickets')
                    ->has('unassigned_tickets')
                    ->has('pending_verifications')
                    ->has('pending_listings')
                    ->has('open_disputes')
                    ->has('pending_withdrawals')
                    ->has('approved_withdrawals')
                    ->has('suspended_users')
                )
                ->has('recentOrders')
                ->has('recentTickets')
            );
    }

    /** An admin user — a Role with is_admin_role passes the `admin` middleware. */
    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $role = Role::create([
            'name'          => 'ckpt-admin-'.$user->id,
            'display_name'  => 'Administrator',
            'is_admin_role' => true,
        ]);
        $user->roles()->attach($role->id);

        return $user;
    }

    /**
     * A super-admin — holds the seeded `admin` role, which Gate::before grants
     * every ability. Needed for pages that authorize a specific permission (e.g.
     * `users.view`) rather than only passing the `admin` middleware; makeAdmin()'s
     * uniquely-named role carries no permissions and would 403 on those.
     */
    private function makeSuperAdmin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'admin')->value('id'));

        return $user;
    }

    /**
     * Admin/Orders/Index.vue reads a whitelisted `orders` paginator, the echoed
     * `filters` (so the search box + status select follow the URL) and a
     * `statuses` option list.
     */
    public function test_admin_orders_index_renders_the_filterable_list(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get('/admin/orders?status=disputed&q=ORD-123')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Orders/Index')
                ->has('orders.data')
                ->where('filters.status', 'disputed')
                ->where('filters.q', 'ORD-123')
                ->has('statuses', 6)
                ->has('statuses.0', fn (Assert $s) => $s
                    ->where('value', 'pending_payment')
                    ->where('label', 'Pending Payment')
                )
            );
    }

    /**
     * Admin/Withdrawals/Index.vue reads a whitelisted `withdrawals` paginator,
     * the echoed `filters` (status defaults to 'pending') and a `statuses` list.
     */
    public function test_admin_withdrawals_index_renders_the_filterable_list(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get('/admin/withdrawals?status=rejected&q=jane')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Withdrawals/Index')
                ->has('withdrawals.data')
                ->where('filters.status', 'rejected')
                ->where('filters.q', 'jane')
                ->has('statuses', 5)
                ->has('statuses.0', fn (Assert $s) => $s
                    ->where('value', 'pending')
                    ->where('label', 'Pending')
                )
            );
    }

    /**
     * Admin/Users/Index.vue reads a whitelisted `users` paginator, the echoed
     * `filters` (search + status + verification, each defaulting to 'all') and the
     * `statuses` / `verifications` option lists that drive the two selects.
     * Index authorizes `users.view`, so it needs a super-admin, not makeAdmin().
     */
    public function test_admin_users_index_renders_the_filterable_list(): void
    {
        $this->actingAs($this->makeSuperAdmin())
            ->get('/admin/users?status=suspended&verification=pending&q=jane')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Users/Index')
                ->has('users.data')
                ->where('filters.status', 'suspended')
                ->where('filters.verification', 'pending')
                ->where('filters.q', 'jane')
                ->has('statuses', 3)
                ->has('statuses.0', fn (Assert $s) => $s
                    ->where('value', 'active')
                    ->where('label', 'Active')
                )
                ->has('verifications', 3)
                ->has('verifications.0', fn (Assert $v) => $v
                    ->where('value', 'approved')
                    ->where('label', 'Verified')
                )
            );
    }

    /**
     * Show whitelists the profile, formats wallet money server-side (a walletless
     * member still gets a well-formed zero) and ships the relationship counts.
     */
    public function test_admin_users_show_renders_the_profile(): void
    {
        $member = User::factory()->create(['name' => 'Jane Buyer']);

        $this->actingAs($this->makeSuperAdmin())
            ->get('/admin/users/'.$member->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Users/Show')
                ->where('user.id', $member->id)
                ->where('user.name', 'Jane Buyer')
                ->where('user.status', 'active')
                ->where('user.verification', 'not_submitted')
                ->where('user.is_admin', false)
                ->where('wallet.available_formatted', '৳0.00')
                ->where('wallet.pending_formatted', '৳0.00')
                ->has('counts', fn (Assert $c) => $c
                    ->where('listings', 0)
                    ->where('purchases', 0)
                    ->where('sales', 0)
                )
            );
    }

    /**
     * The suspend action is functional now — the Blade form sent no reason and
     * 422'd. It requires a reason, flips the status to suspended and redirects
     * back with the flash the shared prop surfaces.
     */
    public function test_admin_can_suspend_a_marketplace_user_with_a_reason(): void
    {
        $member = User::factory()->create();

        $this->actingAs($this->makeSuperAdmin())
            ->from('/admin/users/'.$member->id)
            ->post('/admin/users/'.$member->id.'/suspend', ['reason' => 'Fraudulent activity'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('suspended', $member->fresh()->status->value);
    }

    /** Suspend still validates: an empty reason comes back as a field error. */
    public function test_admin_users_suspend_requires_a_reason(): void
    {
        $member = User::factory()->create();

        $this->actingAs($this->makeSuperAdmin())
            ->from('/admin/users/'.$member->id)
            ->post('/admin/users/'.$member->id.'/suspend', ['reason' => ''])
            ->assertRedirect('/admin/users/'.$member->id)
            ->assertSessionHasErrors('reason');

        $this->assertSame('active', $member->fresh()->status->value);
    }

    /**
     * A seller verification fixture. SellerVerification has no factory, so build
     * it with create(); only user_id is strictly required. nid_number is set (and
     * encrypted at rest by the model) precisely so the show test can prove it
     * never reaches the client.
     */
    private function seedVerification(array $overrides = []): SellerVerification
    {
        $applicant = User::factory()->create(['name' => 'Kamrul Seller']);

        return SellerVerification::create(array_merge([
            'user_id'        => $applicant->id,
            'document_type'  => 'nid',
            'nid_number'     => '1990123456789',
            'date_of_birth'  => '1990-05-15',
            'document_path'  => 'verifications/'.$applicant->id.'/front.jpg',
            'status'         => 'pending',
            'submitted_at'   => now(),
            'attempt_number' => 1,
        ], $overrides));
    }

    /**
     * Admin/Verification/Index.vue reads a whitelisted `verifications` paginator,
     * the echoed `tab` (the status filter the tab links follow) and a `tabs`
     * option list. index() has no authorize() — the `admin` middleware is enough,
     * so makeAdmin() suffices here.
     */
    public function test_admin_verification_index_renders_the_tabbed_list(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get('/admin/verification?tab=approved')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Verification/Index')
                ->has('verifications.data')
                ->where('tab', 'approved')
                ->has('tabs', 3)
                ->has('tabs.0', fn (Assert $t) => $t
                    ->where('value', 'pending')
                    ->where('label', 'Pending')
                )
            );
    }

    /**
     * show() whitelists the applicant + review meta and maps document_type to a
     * label. Two security guarantees are asserted: the encrypted NID is never
     * serialized, and a reviewer who is not the platform owner gets neither the
     * canViewDocuments flag nor the streamable document URL.
     */
    public function test_admin_verification_show_renders_the_review_without_leaking_the_nid(): void
    {
        $verification = $this->seedVerification();

        $this->actingAs($this->makeSuperAdmin())
            ->get('/admin/verification/'.$verification->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Verification/Show')
                ->where('verification.user_name', 'Kamrul Seller')
                ->where('verification.type_label', 'National ID (NID)')
                ->where('verification.status', 'pending')
                ->where('verification.is_pending', true)
                ->where('verification.date_of_birth', '15 May 1990')
                ->where('verification.has_document', true)
                // The NID must never reach the browser, encrypted or otherwise.
                ->missing('verification.nid_number')
                // makeSuperAdmin() holds the `admin` role (super-admin via
                // Gate::before) but not the literal `super_admin` role, so it
                // still cannot view the ID documents.
                ->where('canViewDocuments', false)
                ->where('verification.document_url', null)
            );
    }

    /** Only the platform owner (the seeded `super_admin` role) may view the ID documents. */
    public function test_admin_verification_documents_are_gated_to_the_platform_owner(): void
    {
        $verification = $this->seedVerification();

        $owner = User::factory()->create();
        $owner->roles()->attach(Role::where('name', 'super_admin')->value('id'));

        $this->actingAs($owner)
            ->get('/admin/verification/'.$verification->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canViewDocuments', true)
                ->has('verification.document_url')
            );
    }

    /**
     * Admin/Payments/Index.vue reads a whitelisted `payments` paginator, the
     * echoed `filters` (search box + status select) and a `statuses` option list.
     * index() has no authorize() — the `admin` middleware is enough. Payment has
     * no factory (and the Order FK it needs has a broken one too — see
     * [[market-pre-existing-issues]]), so this asserts the render + filter
     * contract against an empty result set; vue-tsc covers the row shape.
     */
    public function test_admin_payments_index_renders_the_filterable_list(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get('/admin/payments?status=refunded&q=ORD-9')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Payments/Index')
                ->has('payments.data')
                ->where('filters.status', 'refunded')
                ->where('filters.q', 'ORD-9')
                ->has('statuses', 5)
                ->has('statuses.0', fn (Assert $s) => $s
                    ->where('value', 'pending')
                    ->where('label', 'Pending')
                )
            );
    }

    /**
     * Admin/Offers/Index.vue reads a whitelisted `offers` paginator, the echoed
     * `filters` (a single status select — offers have no search box) and a
     * `statuses` option list. index() has no authorize(). Offer has no factory,
     * so — like Orders/Payments — this asserts the render + filter contract
     * against an empty result set; vue-tsc covers the row shape.
     */
    public function test_admin_offers_index_renders_the_filterable_list(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get('/admin/offers?status=accepted')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Offers/Index')
                ->has('offers.data')
                ->where('filters.status', 'accepted')
                ->has('statuses', 4)
                ->has('statuses.0', fn (Assert $s) => $s
                    ->where('value', 'pending')
                    ->where('label', 'Pending')
                )
            );
    }

    /**
     * A wallet fixture. Wallet's factory is broken (HasFactory imported but the
     * trait is never applied — see [[market-pre-existing-issues]]), so build it
     * with create(); there's no wallet auto-create observer to collide with.
     */
    private function seedWallet(int $available = 0, int $pending = 0, array $userOverrides = []): Wallet
    {
        $member = User::factory()->create($userOverrides);

        return Wallet::create([
            'user_id'           => $member->id,
            'available_balance' => $available,
            'pending_balance'   => $pending,
            'currency'          => 'BDT',
        ]);
    }

    /**
     * Admin/Wallets/Index.vue reads a whitelisted `wallets` paginator (ordered by
     * balance) + the echoed `q` filter, with server-formatted money. index()
     * authorizes payments.view, so it needs makeSuperAdmin().
     */
    public function test_admin_wallets_index_renders_the_searchable_list(): void
    {
        $this->seedWallet(123456, 5000, ['name' => 'Rich Seller']);

        $this->actingAs($this->makeSuperAdmin())
            ->get('/admin/wallets?q=Rich')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Wallets/Index')
                ->where('filters.q', 'Rich')
                ->has('wallets.data', 1)
                ->has('wallets.data.0', fn (Assert $w) => $w
                    ->where('user_name', 'Rich Seller')
                    ->where('available', '৳1,234.56')
                    ->where('pending', '৳50.00')
                    ->where('total', '৳1,284.56')
                    ->etc()
                )
            );
    }

    /** Show whitelists the balance summary + a transaction ledger paginator. */
    public function test_admin_wallets_show_renders_the_balance_and_ledger(): void
    {
        $wallet = $this->seedWallet(100000, 0, ['name' => 'Kamrul Seller']);

        $this->actingAs($this->makeSuperAdmin())
            ->get('/admin/wallets/'.$wallet->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Wallets/Show')
                ->where('wallet.user_name', 'Kamrul Seller')
                ->where('wallet.available', '৳1,000.00')
                ->where('wallet.total', '৳1,000.00')
                ->has('transactions.data')
            );
    }

    /**
     * The manual adjustment is a real state change (adminAdjust is pure DB — no
     * SMS/Telegram), so this is a full round-trip: a ৳100 credit lands on the
     * balance and flashes success.
     */
    public function test_admin_can_adjust_a_wallet_balance(): void
    {
        $wallet = $this->seedWallet(5000); // ৳50.00

        $this->actingAs($this->makeSuperAdmin())
            ->from('/admin/wallets/'.$wallet->id)
            ->post('/admin/wallets/'.$wallet->id.'/adjust', [
                'amount_bdt' => '100',
                'reason'     => 'Compensation for a delayed payout.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        // ৳50.00 + ৳100.00 credited => ৳150.00 = 15000 poisha.
        $this->assertSame(15000, $wallet->fresh()->available_balance);
    }

    /** The adjustment validates: a too-short reason comes back as a field error, balance untouched. */
    public function test_admin_wallet_adjustment_requires_a_reason(): void
    {
        $wallet = $this->seedWallet(5000);

        $this->actingAs($this->makeSuperAdmin())
            ->from('/admin/wallets/'.$wallet->id)
            ->post('/admin/wallets/'.$wallet->id.'/adjust', [
                'amount_bdt' => '100',
                'reason'     => 'short',
            ])
            ->assertRedirect('/admin/wallets/'.$wallet->id)
            ->assertSessionHasErrors('reason');

        $this->assertSame(5000, $wallet->fresh()->available_balance);
    }

    // ── Admin disputes (checkpoint 31) ───────────────────────────────────

    /**
     * A resolvable (open) dispute over a real order. Order's factory is broken
     * (see [[market-pre-existing-issues]]) so the order graph is built with
     * create(); Asset::factory() works and supplies the seller-owned listing.
     * ৳2,500.00 buyer_total / ৳2,250.00 seller_earning in poisha.
     */
    private function seedDispute(array $disputeOverrides = []): Dispute
    {
        $category = Category::create([
            'name' => 'Disputed Goods', 'slug' => 'disputed-goods', 'is_active' => true, 'position' => 1,
        ]);
        $seller = User::factory()->create(['name' => 'Selim Seller']);
        $buyer  = User::factory()->create(['name' => 'Bilkis Buyer']);
        $asset  = Asset::factory()->create([
            'user_id' => $seller->id, 'category_id' => $category->id, 'title' => 'Disputed Page',
        ]);

        $order = Order::create([
            'reference'           => 'REF-DSP-'.$buyer->id,
            'order_number'        => 'ORD-DSP-'.$buyer->id,
            'buyer_user_id'       => $buyer->id,
            'seller_user_id'      => $seller->id,
            'asset_id'            => $asset->id,
            'quantity'            => 1,
            'unit_price'          => 250000,
            'subtotal'            => 250000,
            'seller_fee_bp'       => 1000,
            'seller_fee_amount'   => 25000,
            'platform_commission' => 25000,
            'buyer_total'         => 250000,
            'seller_earning'      => 225000,
            'currency'            => 'BDT',
        ]);

        return Dispute::create(array_merge([
            'order_id'  => $order->id,
            'opened_by' => $buyer->id,
            'reason'    => 'Item not as described.',
            'status'    => 'open',
        ], $disputeOverrides));
    }

    /**
     * Admin/Disputes/Index.vue reads a whitelisted `disputes` paginator, the echoed
     * `filters.status` (the active tab, defaulting to 'open') and a `tabs` option
     * list. index() has no authorize() — the `admin` middleware is enough.
     */
    public function test_admin_disputes_index_renders_the_tabbed_list(): void
    {
        $this->seedDispute();

        $this->actingAs($this->makeAdmin())
            ->get('/admin/disputes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Disputes/Index')
                ->where('filters.status', 'open')
                ->has('tabs', 6)
                ->has('tabs.0', fn (Assert $t) => $t
                    ->where('value', 'open')
                    ->where('label', 'Open')
                )
                ->has('disputes.data', 1)
                ->has('disputes.data.0', fn (Assert $d) => $d
                    ->where('buyer', 'Bilkis Buyer')
                    ->where('seller', 'Selim Seller')
                    ->where('order_total', '৳2,500.00')
                    ->where('status', 'open')
                    ->where('status_label', 'Open')
                    ->etc()
                )
            );
    }

    /**
     * show() whitelists the dispute + order summary and formats money server-side.
     * An open dispute reports is_open (the Vue swaps in the resolution panel), and
     * with nothing attached the evidence + order-messages lists are empty.
     */
    public function test_admin_disputes_show_renders_the_review(): void
    {
        $dispute = $this->seedDispute();

        $this->actingAs($this->makeAdmin())
            ->get('/admin/disputes/'.$dispute->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Disputes/Show')
                ->where('dispute.status', 'open')
                ->where('dispute.is_open', true)
                ->where('dispute.reason', 'Item not as described.')
                ->where('dispute.resolution_type', null)
                ->where('order.buyer', 'Bilkis Buyer')
                ->where('order.seller', 'Selim Seller')
                ->where('order.buyer_total', '৳2,500.00')
                ->where('order.seller_earning', '৳2,250.00')
                ->has('order.buyer_total_bdt')
                ->has('evidence', 0)
                ->has('messages', 0)
            );
    }

    /**
     * The full-refund resolution is a real money movement (DisputeService credits
     * the buyer via WalletService — pure DB, no notifications), so this is a full
     * round-trip: the buyer's wallet gains the whole buyer_total and the dispute is
     * marked resolved. Authorizes disputes.manage, so it needs makeSuperAdmin().
     */
    public function test_admin_can_resolve_a_dispute_with_a_full_refund(): void
    {
        $dispute = $this->seedDispute();
        $buyerId = $dispute->order->buyer_user_id;
        Wallet::create([
            'user_id' => $buyerId, 'available_balance' => 0, 'pending_balance' => 0, 'currency' => 'BDT',
        ]);

        $this->actingAs($this->makeSuperAdmin())
            ->from('/admin/disputes/'.$dispute->id)
            ->post('/admin/disputes/'.$dispute->id.'/full-refund', [
                'note' => 'Buyer proved the page was misrepresented.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        // ৳2,500.00 buyer_total credited back to the buyer.
        $this->assertSame(250000, Wallet::where('user_id', $buyerId)->value('available_balance'));

        $fresh = $dispute->fresh();
        $this->assertSame('resolved', $fresh->status->value);
        $this->assertSame('full_refund', $fresh->resolution_type);
        $this->assertSame(250000, $fresh->resolution_amount);
    }

    /**
     * Updating a dispute's status is a pure DB write (no wallet movement); the
     * PATCH flips the status and flashes success. Authorizes disputes.manage.
     */
    public function test_admin_can_update_a_dispute_status(): void
    {
        $dispute = $this->seedDispute();

        $this->actingAs($this->makeSuperAdmin())
            ->from('/admin/disputes/'.$dispute->id)
            ->patch('/admin/disputes/'.$dispute->id.'/status', [
                'status' => 'under_review',
                'note'   => 'Escalated to a senior agent for review.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('under_review', $dispute->fresh()->status->value);
    }

    public function test_contact_renders_the_inertia_page(): void
    {
        $this->get('/contact')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Contact'));
    }

    /** Contact.vue reads form.errors.* to render field messages. */
    public function test_contact_submit_returns_validation_errors_for_an_empty_form(): void
    {
        $this->from('/contact')
            ->post('/contact', ['name' => '', 'email' => '', 'message' => ''])
            ->assertRedirect('/contact')
            ->assertSessionHasErrors(['name', 'email', 'message']);
    }

    public function test_contact_submit_rejects_a_malformed_email(): void
    {
        $this->from('/contact')
            ->post('/contact', ['name' => 'Ada', 'email' => 'not-an-email', 'message' => 'Hello'])
            ->assertRedirect('/contact')
            ->assertSessionHasErrors('email');
    }

    /** PublicLayout renders the shared flash prop, so the success path must set it. */
    public function test_contact_submit_flashes_success_and_the_flash_reaches_inertia(): void
    {
        $this->from('/contact')
            ->post('/contact', [
                'name'    => 'Ada Lovelace',
                'email'   => 'ada@example.com',
                'message' => 'Question about seller verification.',
            ])
            ->assertRedirect('/contact')
            ->assertSessionHas('success');

        // Follow the redirect: the flash must arrive as an Inertia prop.
        $this->get('/contact')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contact')
                ->has('flash.success')
            );
    }

    public static function legalSlugs(): array
    {
        return [
            ['terms', 'Terms of Service'],
            ['privacy', 'Privacy Policy'],
            ['buyer-protection', 'Buyer Protection'],
            ['seller-policy', 'Seller Policy'],
            ['refund-policy', 'Refund Policy'],
            ['dispute-policy', 'Dispute Policy'],
            ['prohibited-assets', 'Prohibited Assets'],
        ];
    }

    #[DataProvider('legalSlugs')]
    public function test_every_legal_page_renders_the_inertia_component(string $slug, string $title): void
    {
        $this->get("/legal/{$slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Legal')
                ->where('page.slug', $slug)
                ->where('page.title', $title)
                ->has('page.body')
                ->has('lastUpdated')
            );
    }

    public function test_an_unknown_legal_slug_404s(): void
    {
        $this->get('/legal/not-a-real-policy')->assertNotFound();
    }

    /**
     * Category is created with create() rather than a factory on purpose:
     * Category has no factory (a pre-existing gap), and AssetFactory falls back
     * to Category::first(), so this keeps the migration tests independent of it.
     */
    private function seedOneAsset(): array
    {
        $category = Category::create([
            'name'      => 'Social Pages',
            'slug'      => 'social-pages',
            'icon'      => '📣',
            'is_active' => true,
            'position'  => 1,
        ]);

        $asset = Asset::factory()->create([
            'category_id' => $category->id,
            'title'       => 'Established Cooking Page',
            'price'       => 250000, // poisha -> ৳2,500.00
        ]);

        return [$category, $asset];
    }

    public function test_home_renders_the_inertia_page_with_mapped_props(): void
    {
        $this->seedOneAsset();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home')
                ->has('categories', 1)
                ->has('categories.0', fn (Assert $c) => $c
                    ->where('slug', 'social-pages')
                    ->where('name', 'Social Pages')
                    ->where('icon', '📣')
                    ->where('children_count', 0)
                )
                ->has('latestAssets', 1)
                ->has('featuredAssets', 0)
                ->has('latestAssets.0', fn (Assert $a) => $a
                    ->where('title', 'Established Cooking Page')
                    // Money::format owns currency rendering; the client must not re-derive it.
                    ->where('price_formatted', '৳2,500.00')
                    ->where('is_sold_out', false)
                    ->where('is_featured', false)
                    ->etc()
                )
            );
    }

    /** The asset payload is a whitelist — extra columns must not reach the client. */
    public function test_home_asset_payload_does_not_leak_model_columns(): void
    {
        $this->seedOneAsset();

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('latestAssets.0', fn (Assert $a) => $a
                    ->missing('description')
                    ->missing('user_id')
                    ->missing('status')
                    ->missing('views_count')
                    ->missing('sold_quantity')
                    ->missing('created_at')
                    ->etc()
                )
            );
    }

    public function test_home_renders_with_no_categories_or_assets(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Home')
                ->has('categories', 0)
                ->has('featuredAssets', 0)
                ->has('latestAssets', 0)
            );
    }

    public function test_marketplace_renders_the_inertia_page_with_a_paginator(): void
    {
        [$category] = $this->seedOneAsset();
        Asset::factory()->count(13)->create(['category_id' => $category->id]);

        $this->get('/marketplace')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Marketplace/Index')
                // paginate(12) over 14 assets
                ->has('assets.data', 12)
                ->where('assets.total', 14)
                ->where('assets.current_page', 1)
                ->where('assets.last_page', 2)
                ->has('assets.links')
                ->has('rootCategories', 1)
                ->has('sortOptions')
            );

        $this->get('/marketplace?page=2')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('assets.data', 2)
                ->where('assets.current_page', 2)
            );
    }

    /** The Vue filter form is controlled by these, so they must round-trip. */
    public function test_marketplace_echoes_the_filter_state_back(): void
    {
        $this->seedOneAsset();

        $this->get('/marketplace?q=cooking&verified_only=1&min_price=100&sort=price_asc')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.q', 'cooking')
                ->where('filters.verified_only', true)
                ->where('filters.featured_only', false)
                ->where('filters.min_price', '100')
                ->where('filters.sort', 'price_asc')
            );
    }

    /** ALLOWED_SORTS is a whitelist — anything else must fall back, not reach the query. */
    public function test_marketplace_falls_back_to_newest_for_an_unknown_sort(): void
    {
        $this->seedOneAsset();

        $this->get('/marketplace?sort=price_asc;DROP+TABLE+assets')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('filters.sort', 'newest'));
    }

    public function test_marketplace_search_narrows_the_results(): void
    {
        [$category] = $this->seedOneAsset();
        Asset::factory()->create(['category_id' => $category->id, 'title' => 'Premium Domain Name']);

        $this->get('/marketplace?q=Cooking')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('assets.data', 1)
                ->where('assets.data.0.title', 'Established Cooking Page')
            );
    }

    public function test_marketplace_flags_the_authenticated_users_favorites(): void
    {
        [, $asset] = $this->seedOneAsset();
        $user = User::factory()->create();
        Favorite::create(['user_id' => $user->id, 'asset_id' => $asset->id]);

        // Guest first: actingAs() persists for the rest of the test, so the
        // unauthenticated assertion has to come before it.
        $this->get('/marketplace')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('assets.data.0.is_favorited', false));

        $this->actingAs($user)
            ->get('/marketplace')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('assets.data.0.is_favorited', true));
    }

    public function test_asset_show_renders_the_inertia_page(): void
    {
        [, $asset] = $this->seedOneAsset();

        $this->get("/asset/{$asset->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Marketplace/Show')
                ->where('asset.slug', $asset->slug)
                ->where('asset.title', 'Established Cooking Page')
                ->where('asset.price_formatted', '৳2,500.00')
                ->where('asset.is_purchasable', true)
                ->where('isFavorited', false)
                // Ownership is decided server-side, never inferred from an id on the client.
                ->where('canManage', false)
                ->where('manageUrl', null)
                ->where('activeOffer', null)
                ->has('asset.images')
                ->has('seo.canonical')
            );
    }

    public function test_asset_show_marks_the_owner_as_able_to_manage(): void
    {
        [, $asset] = $this->seedOneAsset();

        $this->actingAs($asset->seller)
            ->get("/asset/{$asset->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canManage', true)
                ->has('manageUrl')
            );
    }

    public function test_asset_show_404s_for_an_unknown_slug(): void
    {
        $this->get('/asset/no-such-asset')->assertNotFound();
    }

    public function test_profile_show_renders_the_listings_tab_by_default(): void
    {
        [, $asset] = $this->seedOneAsset();
        $seller = $asset->seller;

        $this->get('/users/'.($seller->username ?? $seller->id))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Show')
                ->where('profile.name', $seller->name)
                ->where('tab', 'listings')
                ->has('assets.data', 1)
                ->where('assets.data.0.slug', $asset->slug)
                ->where('reviews', null)
                ->where('isOwnProfile', false)
                ->has('stats.listed')
            );
    }

    public function test_profile_show_switches_to_the_reviews_tab(): void
    {
        [, $asset] = $this->seedOneAsset();
        $seller = $asset->seller;

        $this->get('/users/'.($seller->username ?? $seller->id).'?tab=reviews')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tab', 'reviews')
                // Only the active tab is queried, so the asset prop stays null.
                ->where('assets', null)
                ->has('reviews.data', 0)
            );
    }

    public function test_profile_show_404s_for_an_unknown_user(): void
    {
        $this->get('/users/nobody-here')->assertNotFound();
    }

    // ── Auth pages ───────────────────────────────────────────────────────
    // The signup and password-reset flows are three-step and session-gated:
    // each GET must both render its Vue component and keep the server-side
    // guard that redirects when an earlier step was skipped.

    public function test_login_renders_the_inertia_page(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
    }

    /** Login.vue renders both a rejected password and unknown credentials under `email`. */
    public function test_login_surfaces_bad_credentials_as_an_email_error(): void
    {
        $this->from('/login')
            ->post('/login', ['email' => 'nobody@example.com', 'password' => 'whatever'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_register_step_one_renders_the_inertia_page(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Register'));
    }

    public function test_register_verify_redirects_when_the_phone_step_was_skipped(): void
    {
        $this->get('/register/verify')->assertRedirect(route('register'));
    }

    public function test_register_verify_renders_the_phone_it_sent_the_code_to(): void
    {
        $this->withSession(['register_phone' => '01711234567'])
            ->get('/register/verify')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/RegisterVerify')
                ->where('phone', '01711234567')
            );
    }

    /** Holding a phone in the session is not enough — it has to be OTP-verified. */
    public function test_register_details_redirects_until_the_phone_is_verified(): void
    {
        $this->get('/register/details')->assertRedirect(route('register'));

        $this->withSession(['register_phone' => '01711234567'])
            ->get('/register/details')
            ->assertRedirect(route('register'));
    }

    public function test_register_details_renders_the_verified_phone(): void
    {
        $this->withSession(['register_phone' => '01711234567', 'register_phone_verified' => true])
            ->get('/register/details')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/RegisterDetails')
                ->where('phone', '01711234567')
            );
    }

    /** End-to-end across all three steps, driving the real OTP the controller stored. */
    public function test_the_whole_register_flow_creates_an_account(): void
    {
        $this->post('/register/send-otp', ['phone' => '01711234567'])
            ->assertRedirect(route('register.verify'))
            ->assertSessionHas('register_phone', '01711234567');

        $otp = PhoneOtp::where('phone', '01711234567')->latest()->firstOrFail();

        $this->post('/register/verify-otp', ['otp' => $otp->otp])
            ->assertRedirect(route('register.details'))
            ->assertSessionHas('register_phone_verified', true);

        $this->post('/register', [
            'first_name'            => 'Ada',
            'last_name'             => 'Lovelace',
            'email'                 => 'ada@example.com',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect(route('home'));

        $this->assertDatabaseHas('users', [
            'email' => 'ada@example.com',
            'name'  => 'Ada Lovelace',
            'phone' => '01711234567',
        ]);
        $this->assertAuthenticated();

        // The session keys are cleared so the flow cannot be replayed.
        $this->assertNull(session('register_phone'));
        $this->assertNull(session('register_phone_verified'));
    }

    public function test_a_wrong_otp_comes_back_as_an_otp_error(): void
    {
        $this->post('/register/send-otp', ['phone' => '01711234567'])
            ->assertRedirect(route('register.verify'));

        // 000000 collides with a real code only if random_int returned it; the
        // generator's range starts at 100000, so it never can.
        $this->from('/register/verify')
            ->post('/register/verify-otp', ['otp' => '000000'])
            ->assertRedirect('/register/verify')
            ->assertSessionHasErrors('otp');
    }

    public function test_forgot_password_renders_the_inertia_page(): void
    {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/ForgotPassword'));
    }

    public function test_reset_verify_redirects_when_the_phone_step_was_skipped(): void
    {
        $this->get('/forgot-password/verify')->assertRedirect(route('password.request'));
    }

    public function test_reset_verify_renders_the_phone_it_sent_the_code_to(): void
    {
        $this->withSession(['reset_phone' => '01711234567'])
            ->get('/forgot-password/verify')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/ResetVerify')
                ->where('phone', '01711234567')
            );
    }

    public function test_reset_password_redirects_until_the_phone_is_verified(): void
    {
        $this->get('/forgot-password/reset')->assertRedirect(route('password.request'));

        $this->withSession(['reset_phone' => '01711234567'])
            ->get('/forgot-password/reset')
            ->assertRedirect(route('password.request'));
    }

    public function test_reset_password_renders_the_inertia_page(): void
    {
        $this->withSession(['reset_phone' => '01711234567', 'reset_phone_verified' => true])
            ->get('/forgot-password/reset')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/ResetPassword'));
    }

    public function test_forgot_password_rejects_an_unknown_number(): void
    {
        $this->from('/forgot-password')
            ->post('/forgot-password', ['phone' => '01711234567'])
            ->assertRedirect('/forgot-password')
            ->assertSessionHasErrors('phone');
    }

    public function test_verify_email_renders_for_an_authenticated_user(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $this->actingAs($user)
            ->get('/verify-email')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/VerifyEmail')
                // VerifyEmail.vue names the address from the shared auth prop.
                ->where('auth.user.email', $user->email)
            );
    }

    /**
     * PublicLayout renders the shared `flash.status` prop verbatim, so the resend
     * endpoint must flash a sentence rather than Breeze's 'verification-link-sent'.
     */
    public function test_resending_verification_flashes_a_human_readable_status(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email_verified_at' => null]);

        $this->actingAs($user)
            ->from('/verify-email')
            ->post('/verify-email/send')
            ->assertRedirect('/verify-email')
            ->assertSessionHas('status', fn (string $status) => $status !== 'verification-link-sent'
                && str_contains($status, 'verification link'));

        Notification::assertSentTo($user, VerifyEmailNotification::class);

        $this->actingAs($user)
            ->get('/verify-email')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('flash.status'));
    }

    // ── Dashboard (checkpoint 11) ────────────────────────────────────────

    public function test_dashboard_overview_renders_the_inertia_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Index')
                ->has('stats', fn (Assert $stats) => $stats
                    ->has('available_formatted')
                    ->has('pending_formatted')
                    ->where('listings', 0)
                    ->where('orders', 0)
                )
            );
    }

    /**
     * Money is integer poisha and App\Support\Money owns the formatting, so the
     * client must never receive a raw amount to format itself. A user with no
     * wallet row still gets a well-formed zero.
     */
    public function test_dashboard_overview_formats_wallet_money_server_side(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.available_formatted', '৳0.00')
                ->where('stats.pending_formatted', '৳0.00')
                ->missing('stats.available')
                ->missing('stats.pending')
            );
    }

    /**
     * The Blade original computed these and rendered none of them; the queries
     * were dropped rather than shipped unused. Guards against them creeping back.
     */
    public function test_dashboard_overview_does_not_ship_unused_props(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->missing('stats.pending_offers')
                ->missing('stats.unread_msgs')
                ->missing('recentListings')
                ->missing('recentPurchases')
            );
    }

    public function test_dashboard_settings_renders_the_inertia_page(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->get('/dashboard/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Settings')
                ->where('emailVerified', true)
            );
    }

    public function test_dashboard_settings_reports_an_unverified_email(): void
    {
        $user = User::factory()->create(['email_verified_at' => null]);

        $this->actingAs($user)
            ->get('/dashboard/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('emailVerified', false));
    }

    // ── Dashboard listings create + edit (checkpoint 19b) ────────────────

    /**
     * The create wizard ships a category → subcategory → attribute tree.
     * roots() are the parents; only children carry the selectable flag and the
     * attribute definitions the Vue steps render.
     */
    public function test_dashboard_listings_create_renders_the_inertia_page(): void
    {
        $seller = User::factory()->verified()->create();

        $parent = Category::create([
            'name' => 'Websites', 'slug' => 'websites', 'is_active' => true, 'position' => 1,
        ]);
        $child = Category::create([
            'name' => 'Blogs', 'slug' => 'blogs', 'parent_id' => $parent->id,
            'is_active' => true, 'position' => 1,
        ]);
        CategoryAttribute::create([
            'category_id' => $child->id, 'key' => 'monthly_visitors', 'label' => 'Monthly Visitors',
            'type' => 'number', 'is_required' => true, 'is_active' => true, 'position' => 1,
            'unit' => 'visitors',
        ]);

        $this->actingAs($seller)
            ->get('/dashboard/listings/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Listings/Create')
                ->has('feePercent')
                ->has('categories', 1)
                ->has('categories.0', fn (Assert $c) => $c
                    ->where('name', 'Websites')
                    ->has('children', 1)
                    ->has('children.0', fn (Assert $sub) => $sub
                        ->where('name', 'Blogs')
                        ->where('selectable', true)
                        ->has('attributes', 1)
                        ->has('attributes.0', fn (Assert $a) => $a
                            ->where('label', 'Monthly Visitors')
                            ->where('type', 'number')
                            ->where('is_required', true)
                            ->where('unit', 'visitors')
                            ->etc()
                        )
                        ->etc()
                    )
                    ->etc()
                )
            );
    }

    /**
     * Edit prefills the form from the listing plus its attribute values, and the
     * server — not the client — owns money rendering (price_bdt for the input,
     * price_formatted for the locked display).
     */
    public function test_dashboard_listings_edit_renders_the_inertia_page(): void
    {
        $seller = User::factory()->verified()->create();

        $category = Category::create([
            'name' => 'Websites', 'slug' => 'websites', 'is_active' => true, 'position' => 1,
        ]);
        CategoryAttribute::create([
            'category_id' => $category->id, 'key' => 'niche', 'label' => 'Niche',
            'type' => 'select', 'options' => ['Tech', 'Food'], 'is_active' => true, 'position' => 1,
        ]);

        $asset = Asset::factory()->draft()->create([
            'user_id'            => $seller->id,
            'category_id'        => $category->id,
            'title'              => 'My Draft Blog',
            'price'              => 250000, // poisha -> ৳2,500.00
            'quantity'           => 3,
            'available_quantity' => 3,
        ]);

        $this->actingAs($seller)
            ->get("/dashboard/listings/{$asset->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Listings/Edit')
                ->has('feePercent')
                ->has('listing', fn (Assert $l) => $l
                    ->where('id', $asset->id)
                    ->where('title', 'My Draft Blog')
                    ->where('status', 'draft')
                    ->where('quantity', 3)
                    ->where('price_bdt', '2500')
                    ->where('price_formatted', '৳2,500.00')
                    ->where('is_price_locked', false)
                    ->etc()
                )
                ->has('attributes', 1)
                ->has('attributes.0', fn (Assert $a) => $a
                    ->where('label', 'Niche')
                    ->where('type', 'select')
                    ->has('options', 2)
                    ->where('value', null)
                    ->etc()
                )
            );
    }

    /**
     * Notifications ship a whitelist: the server maps each DatabaseNotification's
     * data bag to a title/message and derives the emoji from the type prefix, so
     * the client never re-implements that mapping. unreadCount drives the tab badge.
     */
    public function test_dashboard_notifications_renders_the_inertia_page(): void
    {
        $user = User::factory()->create();
        $user->notifications()->create([
            'id'      => (string) Str::uuid(),
            'type'    => 'App\\Notifications\\OrderPlaced',
            'data'    => ['type' => 'order.placed', 'title' => 'Order placed', 'message' => 'A buyer placed an order.'],
            'read_at' => null,
        ]);

        $this->actingAs($user)
            ->get('/dashboard/notifications')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Notifications/Index')
                ->where('tab', 'all')
                ->where('unreadCount', 1)
                ->has('notifications.data', 1)
                ->has('notifications.data.0', fn (Assert $n) => $n
                    ->where('title', 'Order placed')
                    ->where('message', 'A buyer placed an order.')
                    ->where('icon', '📦') // order.* -> 📦, mapped server-side
                    ->where('is_read', false)
                    ->has('id')
                    ->has('created_human')
                )
            );
    }

    // ── Dashboard support tickets (checkpoint 21) ───────────────────────

    public function test_dashboard_tickets_renders_the_inertia_page(): void
    {
        $user = User::factory()->create();
        SupportTicket::create([
            'reference'     => 'TKT-20260817-AAA111',
            'user_id'       => $user->id,
            'category'      => 'account',
            'subject'       => 'Cannot access my wallet',
            'priority'      => 'high',
            'status'        => TicketStatus::Open,
            'last_reply_at' => now(),
        ]);

        $this->actingAs($user)
            ->get('/dashboard/tickets')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Tickets/Index')
                ->has('tickets.data', 1)
                ->has('tickets.data.0', fn (Assert $t) => $t
                    ->where('subject', 'Cannot access my wallet')
                    ->where('status', 'open')
                    ->where('priority_label', 'High')
                    // priorityColor() owns the mapping: high -> amber.
                    ->where('priority_color', 'amber')
                    ->has('id')
                    ->has('updated_human')
                )
            );
    }

    public function test_dashboard_tickets_create_renders_the_inertia_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard/tickets/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Dashboard/Tickets/Create'));
    }

    /**
     * Show whitelists the ticket and maps its thread; an Open ticket is
     * replyable, and with nothing linked the context-links list is empty.
     */
    public function test_dashboard_tickets_show_renders_the_thread(): void
    {
        $user   = User::factory()->create();
        $ticket = SupportTicket::create([
            'reference'     => 'TKT-20260817-BBB222',
            'user_id'       => $user->id,
            'category'      => 'account',
            'subject'       => 'Cannot access my wallet',
            'priority'      => 'normal',
            'status'        => TicketStatus::Open,
            'last_reply_at' => now(),
        ]);
        $ticket->messages()->create([
            'user_id'        => $user->id,
            'body'           => 'Please help me access my wallet.',
            'is_staff_reply' => false,
        ]);

        $this->actingAs($user)
            ->get("/dashboard/tickets/{$ticket->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Tickets/Show')
                ->where('ticket.reference', 'TKT-20260817-BBB222')
                ->where('ticket.status', 'open')
                ->where('ticket.priority_label', 'Normal')
                ->where('ticket.priority_color', 'brand')
                ->where('ticket.can_reply', true)
                ->has('ticket.links', 0)
                ->has('ticket.messages', 1)
                ->has('ticket.messages.0', fn (Assert $m) => $m
                    ->where('is_staff', false)
                    ->where('author', $user->name)
                    ->where('body', 'Please help me access my wallet.')
                    ->has('initial')
                    ->has('created_human')
                    ->etc()
                )
            );
    }

    /** The owner check is server-side: a stranger cannot open someone's ticket. */
    public function test_dashboard_tickets_show_forbids_a_non_owner(): void
    {
        $owner  = User::factory()->create();
        $other  = User::factory()->create();
        $ticket = SupportTicket::create([
            'reference'     => 'TKT-20260817-CCC333',
            'user_id'       => $owner->id,
            'category'      => 'account',
            'subject'       => 'Private matter',
            'priority'      => 'low',
            'status'        => TicketStatus::Open,
            'last_reply_at' => now(),
        ]);

        $this->actingAs($other)
            ->get("/dashboard/tickets/{$ticket->id}")
            ->assertForbidden();
    }

    // ── Dashboard messages (checkpoint 22) ──────────────────────────────

    /** A two-party order conversation with one inbound message from $other. */
    private function seedConversation(User $me, User $other): Conversation
    {
        $conversation = Conversation::create([
            'type'            => 'order',
            'last_message_at' => now(),
        ]);
        $conversation->participants()->attach([$me->id, $other->id]);
        $conversation->activeMessages()->create([
            'sender_user_id' => $other->id,
            'body'           => 'Hi, thanks for your order!',
        ]);

        return $conversation;
    }

    /**
     * The list whitelists each conversation to the *other* participant plus an
     * unread count; with no ?conversation the thread props stay empty.
     */
    public function test_dashboard_messages_renders_the_conversation_list(): void
    {
        $me    = User::factory()->create();
        $other = User::factory()->create(['name' => 'Rifat Seller']);
        $this->seedConversation($me, $other);

        $this->actingAs($me)
            ->get('/dashboard/messages')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Messages/Index')
                ->where('selectedId', null)
                ->where('activeConversation', null)
                ->has('messages', 0)
                ->has('conversations.data', 1)
                ->has('conversations.data.0', fn (Assert $c) => $c
                    ->where('other_name', 'Rifat Seller')
                    ->where('other_initial', 'R')
                    ->where('unread', 1)
                    ->has('subtitle')
                    ->has('id')
                    ->etc()
                )
            );
    }

    /**
     * Opening ?conversation={id} maps the thread oldest-first; the acting user's
     * own view marks the inbound message as not "mine", and an order-less
     * conversation reports an "unknown" status with no order link.
     */
    public function test_dashboard_messages_opens_a_conversation_thread(): void
    {
        $me    = User::factory()->create();
        $other = User::factory()->create(['name' => 'Rifat Seller']);
        $conversation = $this->seedConversation($me, $other);

        $this->actingAs($me)
            ->get('/dashboard/messages?conversation='.$conversation->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Messages/Index')
                ->where('selectedId', $conversation->id)
                ->has('activeConversation', fn (Assert $c) => $c
                    ->where('other_name', 'Rifat Seller')
                    ->where('order_status', 'unknown')
                    ->where('order_url', null)
                    ->etc()
                )
                ->has('messages', 1)
                ->has('messages.0', fn (Assert $m) => $m
                    ->where('mine', false)
                    ->where('is_system', false)
                    ->where('body', 'Hi, thanks for your order!')
                    ->where('attachment', null)
                    ->where('reply_to', null)
                    ->has('time')
                    ->has('sender_initial')
                    ->etc()
                )
            );
    }

    /**
     * An Inertia POST carries no Accept: application/json, so send() must fall
     * through to back() (a redirect the router follows) and persist the message
     * rather than returning the bare-JSON branch.
     */
    public function test_dashboard_messages_send_persists_and_redirects(): void
    {
        $me    = User::factory()->create();
        $other = User::factory()->create();
        $conversation = $this->seedConversation($me, $other);

        $this->actingAs($me)
            ->from('/dashboard/messages?conversation='.$conversation->id)
            ->post('/dashboard/messages/'.$conversation->id.'/send', [
                'body'              => 'On my way with the details.',
                'client_message_id' => 'ckpt22-uuid-1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'sender_user_id'  => $me->id,
            'body'            => 'On my way with the details.',
        ]);
    }

    #[DataProvider('dashboardRouteProvider')]
    public function test_dashboard_pages_require_authentication(string $path): void
    {
        $this->get($path)->assertRedirect('/login');
    }

    public static function dashboardRouteProvider(): array
    {
        return [
            'overview' => ['/dashboard'],
            'settings' => ['/dashboard/settings'],
            'listings-create' => ['/dashboard/listings/create'],
            'notifications' => ['/dashboard/notifications'],
            'tickets' => ['/dashboard/tickets'],
            'tickets-create' => ['/dashboard/tickets/create'],
            'messages' => ['/dashboard/messages'],
        ];
    }
}
