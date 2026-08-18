<?php
namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\TicketStatus;
use App\Models\Asset;
use App\Models\Category;
use App\Models\CategoryAttribute;
use App\Models\Conversation;
use App\Models\Dispute;
use App\Models\Favorite;
use App\Models\FraudEvent;
use App\Models\FraudReview;
use App\Models\MessageReport;
use App\Models\Order;
use App\Models\Permission;
use App\Models\PhoneOtp;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\SellerVerification;
use App\Models\SmsLog;
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

    // ── Admin: Categories (checkpoint 32) ─────────────────────────────

    /**
     * Index authorizes `categories.manage`, so it needs a super-admin. The tree
     * is a flat list of roots, each carrying its children (with attr counts).
     */
    public function test_admin_categories_index_renders_the_tree(): void
    {
        $root = Category::create([
            'name' => 'Social Media', 'slug' => 'social-media', 'icon' => '📱',
            'is_active' => true, 'position' => 1,
        ]);
        Category::create([
            'name' => 'Instagram', 'slug' => 'instagram', 'parent_id' => $root->id,
            'is_active' => true, 'position' => 1,
        ]);

        $this->actingAs($this->makeSuperAdmin())
            ->get('/admin/categories')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Categories/Index')
                ->has('categories', 1)
                ->has('categories.0', fn (Assert $c) => $c
                    ->where('name', 'Social Media')
                    ->where('icon', '📱')
                    ->has('children', 1)
                    ->where('children.0.name', 'Instagram')
                    ->etc()
                )
            );
    }

    /** The manage permission is required; a bare admin (no permissions) is denied. */
    public function test_categories_require_the_manage_permission(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get('/admin/categories')
            ->assertForbidden();
    }

    /** Create.vue's parent <select> is fed the active roots as {id,name}. */
    public function test_admin_categories_create_renders_the_form(): void
    {
        Category::create(['name' => 'Apps', 'slug' => 'apps', 'is_active' => true, 'position' => 1]);

        $this->actingAs($this->makeSuperAdmin())
            ->get('/admin/categories/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Categories/Create')
                ->has('parents', 1)
                ->where('parents.0.name', 'Apps')
            );
    }

    /** Edit.vue pre-fills the details form and lists the category's attributes. */
    public function test_admin_categories_edit_renders_the_form_with_its_attributes(): void
    {
        $category = Category::create(['name' => 'Gaming', 'slug' => 'gaming', 'is_active' => true]);
        $category->attributes()->create([
            'key' => 'platform', 'label' => 'Platform', 'type' => 'select',
            'options' => ['PC', 'Console'], 'is_active' => true, 'position' => 0,
        ]);

        $this->actingAs($this->makeSuperAdmin())
            ->get('/admin/categories/'.$category->id.'/edit')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Categories/Edit')
                ->where('category.id', $category->id)
                ->where('category.name', 'Gaming')
                ->has('parents')
                ->has('attributes', 1)
                ->where('attributes.0.key', 'platform')
                ->where('attributes.0.type', 'select')
                ->has('attributeTypes')
            );
    }

    /** store() slugs the name, persists, and redirects to the index with success. */
    public function test_admin_can_create_a_category(): void
    {
        $this->actingAs($this->makeSuperAdmin())
            ->from('/admin/categories/create')
            ->post('/admin/categories', [
                'name'        => 'Digital Goods',
                'icon'        => '💾',
                'description' => 'Downloadable digital products.',
                'position'    => 3,
                'is_active'   => true,
            ])
            ->assertRedirect(route('admin.categories'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('categories', [
            'name'     => 'Digital Goods',
            'slug'     => 'digital-goods',
            'position' => 3,
        ]);
    }

    /** storeAttribute() attaches a dynamic attribute and flashes back with success. */
    public function test_admin_can_add_a_dynamic_attribute_to_a_category(): void
    {
        $category = Category::create(['name' => 'Websites', 'slug' => 'websites', 'is_active' => true]);

        $this->actingAs($this->makeSuperAdmin())
            ->from('/admin/categories/'.$category->id.'/edit')
            ->post('/admin/categories/'.$category->id.'/attributes', [
                'label'    => 'Monthly Revenue',
                'key'      => 'monthly_revenue',
                'type'     => 'number',
                'unit'     => '/month',
                'position' => 0,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('category_attributes', [
            'category_id' => $category->id,
            'key'         => 'monthly_revenue',
            'label'       => 'Monthly Revenue',
            'type'        => 'number',
        ]);
    }

    // ── Admin: Listings (checkpoint 33) ───────────────────────────────

    /**
     * A listing owned by a named seller in a given status. Category has no
     * factory (see [[market-pre-existing-issues]]) so it's built with create();
     * Asset::factory() works and supplies the rest.
     */
    private function seedListing(AssetStatus $status = AssetStatus::PendingReview, array $overrides = []): Asset
    {
        $category = Category::create([
            'name' => 'Listing Category', 'slug' => 'listing-category', 'is_active' => true, 'position' => 1,
        ]);
        $seller = User::factory()->create(['name' => 'Selim Seller']);

        return Asset::factory()->create(array_merge([
            'user_id'     => $seller->id,
            'category_id' => $category->id,
            'title'       => 'Established Cooking Page',
            'price'       => 250000, // poisha -> ৳2,500.00
            'status'      => $status,
        ], $overrides));
    }

    /**
     * Admin/Listings/Index.vue reads a whitelisted `listings` paginator, the
     * echoed `filters.tab` (the active tab, defaulting to 'pending_review') and a
     * `tabs` option list. index() has no authorize() — the `admin` middleware is
     * enough, so makeAdmin() suffices.
     */
    public function test_admin_listings_index_renders_the_tabbed_list(): void
    {
        $this->seedListing(AssetStatus::PendingReview);

        $this->actingAs($this->makeAdmin())
            ->get('/admin/listings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Listings/Index')
                ->where('filters.tab', 'pending_review')
                ->has('tabs', 5)
                ->has('tabs.0', fn (Assert $t) => $t
                    ->where('value', 'pending_review')
                    ->where('label', 'Pending')
                )
                ->has('listings.data', 1)
                ->has('listings.data.0', fn (Assert $l) => $l
                    ->where('title', 'Established Cooking Page')
                    ->where('seller', 'Selim Seller')
                    ->where('price', '৳2,500.00')
                    ->where('status', 'pending_review')
                    ->etc()
                )
            );
    }

    /** The tab filter follows ?tab=; a published listing shows only on that tab. */
    public function test_admin_listings_index_filters_by_tab(): void
    {
        $this->seedListing(AssetStatus::Published);
        $this->actingAs($this->makeAdmin());

        // Default (pending_review) tab: the published listing is filtered out.
        $this->get('/admin/listings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('listings.data', 0));

        // ?tab=published surfaces it.
        $this->get('/admin/listings?tab=published')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.tab', 'published')
                ->has('listings.data', 1)
            );
    }

    /**
     * show() authorizes `listings.view`, so it needs a super-admin. It whitelists
     * the listing detail (money formatted server-side) and ships empty
     * image/attribute/edit lists plus a null pendingEdit for a plain listing.
     */
    public function test_admin_listings_show_renders_the_review(): void
    {
        $listing = $this->seedListing(AssetStatus::PendingReview);

        $this->actingAs($this->makeSuperAdmin())
            ->get('/admin/listings/'.$listing->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Listings/Show')
                ->where('listing.id', $listing->id)
                ->where('listing.title', 'Established Cooking Page')
                ->where('listing.status', 'pending_review')
                ->where('listing.price', '৳2,500.00')
                ->where('listing.seller', 'Selim Seller')
                ->has('listing.marketplace_url')
                ->has('images', 0)
                ->has('attributes', 0)
                ->has('edits', 0)
                ->where('pendingEdit', null)
            );
    }

    /** show() authorizes `listings.view`; a bare admin (no permissions) is denied. */
    public function test_admin_listings_show_requires_the_view_permission(): void
    {
        $listing = $this->seedListing();

        $this->actingAs($this->makeAdmin())
            ->get('/admin/listings/'.$listing->id)
            ->assertForbidden();
    }

    /**
     * Approve is a pure status write + audit log (no notifications), so this is a
     * full round-trip: a pending listing flips to published and flashes success.
     * Authorizes `listings.approve`, so it needs makeSuperAdmin().
     */
    public function test_admin_can_approve_a_listing(): void
    {
        $listing = $this->seedListing(AssetStatus::PendingReview);

        $this->actingAs($this->makeSuperAdmin())
            ->from('/admin/listings/'.$listing->id)
            ->post('/admin/listings/'.$listing->id.'/approve', ['notes' => 'Looks good.'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('published', $listing->fresh()->status->value);
    }

    /** Reject requires a reason: an empty one comes back as a field error, status untouched. */
    public function test_admin_listings_reject_requires_a_reason(): void
    {
        $listing = $this->seedListing(AssetStatus::PendingReview);

        $this->actingAs($this->makeSuperAdmin())
            ->from('/admin/listings/'.$listing->id)
            ->post('/admin/listings/'.$listing->id.'/reject', ['reason' => ''])
            ->assertRedirect('/admin/listings/'.$listing->id)
            ->assertSessionHasErrors('reason');

        $this->assertSame('pending_review', $listing->fresh()->status->value);
    }

    /**
     * Suspend is a pure status write + audit log; a published listing flips to
     * suspended and flashes success. Authorizes `listings.suspend`.
     */
    public function test_admin_can_suspend_a_published_listing(): void
    {
        $listing = $this->seedListing(AssetStatus::Published);

        $this->actingAs($this->makeSuperAdmin())
            ->from('/admin/listings/'.$listing->id)
            ->post('/admin/listings/'.$listing->id.'/suspend')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('suspended', $listing->fresh()->status->value);
    }

    // ── Admin: Promotions (checkpoint 34) ─────────────────────────────

    /**
     * An active promotion over a real listing. Promotion has no factory (the model
     * never applies HasFactory — see [[market-pre-existing-issues]]), so it's built
     * with create(); Asset::factory() supplies the listing and a named seller owns it.
     */
    private function seedPromotion(array $overrides = []): Promotion
    {
        $category = Category::create([
            'name' => 'Promoted Category', 'slug' => 'promoted-category', 'is_active' => true, 'position' => 1,
        ]);
        $seller = User::factory()->create(['name' => 'Selim Seller']);
        $asset  = Asset::factory()->create([
            'user_id' => $seller->id, 'category_id' => $category->id, 'title' => 'Featured Cooking Page',
        ]);

        return Promotion::create(array_merge([
            'asset_id'  => $asset->id,
            'user_id'   => $seller->id,
            'seller_id' => $seller->id,
            'days'      => 7,
            'price'     => 50000, // poisha -> ৳500.00
            'currency'  => 'BDT',
            'starts_at' => now()->subDay(),
            'ends_at'   => now()->addDays(6),
            'status'    => 'active',
            'is_manual' => false,
        ], $overrides));
    }

    /**
     * Admin/Promotions/Index.vue reads a whitelisted `promotions` paginator, the
     * echoed `filters.status` (the active tab, defaulting to 'active') and a `tabs`
     * option list. index() has no authorize() — the `admin` middleware is enough.
     */
    public function test_admin_promotions_index_renders_the_tabbed_list(): void
    {
        $this->seedPromotion();

        $this->actingAs($this->makeAdmin())
            ->get('/admin/promotions')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Promotions/Index')
                ->where('filters.status', 'active')
                ->has('tabs', 4)
                ->has('tabs.0', fn (Assert $t) => $t
                    ->where('value', 'active')
                    ->where('label', 'Active')
                )
                ->has('promotions.data', 1)
                ->has('promotions.data.0', fn (Assert $p) => $p
                    ->where('listing', 'Featured Cooking Page')
                    ->where('seller', 'Selim Seller')
                    ->where('type', 'Paid')
                    ->where('amount', '৳500.00')
                    ->where('status', 'active')
                    ->etc()
                )
            );
    }

    /** The status filter follows ?status=; an active promotion shows only where it belongs. */
    public function test_admin_promotions_index_filters_by_status(): void
    {
        $this->seedPromotion(); // active
        $this->actingAs($this->makeAdmin());

        // A different tab hides it.
        $this->get('/admin/promotions?status=expired')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', 'expired')
                ->has('promotions.data', 0)
            );

        // ?status=all surfaces it.
        $this->get('/admin/promotions?status=all')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', 'all')
                ->has('promotions.data', 1)
            );
    }

    /**
     * unfeature() is a pure DB write + an in-app notification (no SMS/Telegram), so
     * this is a full round-trip: the active promotion flips to cancelled and flashes
     * success. Authorizes `promotions.feature`, so it needs makeSuperAdmin().
     */
    public function test_admin_can_unfeature_a_promotion(): void
    {
        $promotion = $this->seedPromotion();

        $this->actingAs($this->makeSuperAdmin())
            ->from('/admin/promotions')
            ->post('/admin/promotions/'.$promotion->id.'/unfeature')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('cancelled', $promotion->fresh()->status);
    }

    private function seedSmsLog(array $overrides = []): SmsLog
    {
        $user = User::factory()->create(['name' => 'Rakib Receiver']);

        return SmsLog::create(array_merge([
            'user_id'  => $user->id,
            'phone'    => '8801712345678',
            'template' => 'order_paid',
            'message'  => 'Your order is confirmed.',
            'provider' => 'bulksmsbd',
            'status'   => 'sent',
            'attempts' => 1,
            'sent_at'  => now(),
        ], $overrides));
    }

    /**
     * Admin/Notifications/Index.vue reads a whitelisted `stats` object (SMS
     * totals) and a `provider` status. Authorizes `notifications.view`, so it
     * needs makeSuperAdmin().
     */
    public function test_admin_notifications_index_renders_the_stats(): void
    {
        $this->seedSmsLog(['status' => 'sent']);
        $this->seedSmsLog(['status' => 'failed']);

        $this->actingAs($this->makeSuperAdmin())
            ->get('/admin/notifications')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Notifications/Index')
                ->where('stats.total', 2)
                ->where('stats.sent', 1)
                ->where('stats.failed', 1)
                ->where('provider.name', 'BulkSMSBD')
                ->has('provider.enabled')
            );
    }

    /** The notifications page authorizes `notifications.view`; a bare admin 403s. */
    public function test_admin_notifications_index_requires_the_view_permission(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get('/admin/notifications')
            ->assertForbidden();
    }

    /**
     * Admin/Notifications/SmsLogs.vue reads a whitelisted `logs` paginator, the
     * echoed `filters` (so the two selects follow the URL), a `statuses` option
     * list and the `templates` keys. Authorizes `sms.view`.
     */
    public function test_admin_sms_logs_renders_the_filtered_list(): void
    {
        $this->seedSmsLog(['template' => 'order_paid']);

        $this->actingAs($this->makeSuperAdmin())
            ->get('/admin/notifications/sms')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Notifications/SmsLogs')
                ->where('filters.status', 'all')
                ->has('statuses', 3)
                ->has('templates')
                ->has('logs.data', 1)
                ->has('logs.data.0', fn (Assert $log) => $log
                    ->where('user', 'Rakib Receiver')
                    ->where('phone', '880*******678') // maskedPhone(): 3 + 7 masked + 3
                    ->where('template', 'order_paid')
                    ->where('status', 'sent')
                    ->etc()
                )
            );
    }

    /** The status filter follows ?status=; a failed log hides under ?status=sent. */
    public function test_admin_sms_logs_filters_by_status(): void
    {
        $this->seedSmsLog(['status' => 'failed', 'sent_at' => null, 'error_message' => 'Gateway timeout']);
        $this->actingAs($this->makeSuperAdmin());

        // Only 'sent' requested -> the failed log is hidden.
        $this->get('/admin/notifications/sms?status=sent')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', 'sent')
                ->has('logs.data', 0)
            );

        // Requesting 'failed' surfaces it.
        $this->get('/admin/notifications/sms?status=failed')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', 'failed')
                ->has('logs.data', 1)
            );
    }

    private function seedMessageReport(array $overrides = []): MessageReport
    {
        $reporter = User::factory()->create(['name' => 'Rasel Reporter']);
        $sender   = User::factory()->create(['name' => 'Sadia Sender']);

        $conversation = Conversation::create(['type' => 'order', 'last_message_at' => now()]);
        $conversation->participants()->attach([$reporter->id, $sender->id]);
        $message = $conversation->activeMessages()->create([
            'sender_user_id' => $sender->id,
            'body'           => 'Let us finish this deal outside the platform to save fees.',
        ]);

        return MessageReport::create(array_merge([
            'message_id'  => $message->id,
            'reporter_id' => $reporter->id,
            'reason'      => 'scam',
            'description' => 'Trying to move the transaction off-platform.',
            'status'      => 'pending',
        ], $overrides));
    }

    /**
     * Admin/MessageReports/Index.vue reads a whitelisted `reports` paginator, the
     * echoed `filters.status` (active tab, defaulting to 'pending') and a `tabs`
     * option list. index() authorizes `disputes.manage`, so it needs
     * makeSuperAdmin(); an order-less conversation reports a null order link.
     */
    public function test_admin_message_reports_index_renders_the_tabbed_list(): void
    {
        $this->seedMessageReport();

        $this->actingAs($this->makeSuperAdmin())
            ->get('/admin/message-reports')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/MessageReports/Index')
                ->where('filters.status', 'pending')
                ->has('tabs', 5)
                ->has('tabs.0', fn (Assert $t) => $t
                    ->where('value', 'pending')
                    ->where('label', 'Pending')
                )
                ->has('reports.data', 1)
                ->has('reports.data.0', fn (Assert $r) => $r
                    ->where('reporter', 'Rasel Reporter')
                    ->where('sender', 'Sadia Sender')
                    ->where('reason', 'Scam')
                    ->where('status', 'pending')
                    ->where('order_number', null)
                    ->where('order_url', null)
                    ->etc()
                )
            );
    }

    /** The page authorizes `disputes.manage`; a bare admin 403s. */
    public function test_admin_message_reports_index_requires_the_manage_permission(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get('/admin/message-reports')
            ->assertForbidden();
    }

    /** The status filter follows ?status=; a pending report shows only where it belongs. */
    public function test_admin_message_reports_index_filters_by_status(): void
    {
        $this->seedMessageReport(); // pending
        $this->actingAs($this->makeSuperAdmin());

        // A different tab hides it.
        $this->get('/admin/message-reports?status=dismissed')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', 'dismissed')
                ->has('reports.data', 0)
            );

        // ?status=all surfaces it.
        $this->get('/admin/message-reports?status=all')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', 'all')
                ->has('reports.data', 1)
            );
    }

    /**
     * review() is a pure DB write + audit-log entries (no SMS/Telegram), so this is
     * a full round-trip: a dismissed report flips to 'dismissed', records the
     * reviewer, and flashes success. Authorizes `disputes.manage`.
     */
    public function test_admin_can_review_a_message_report(): void
    {
        $report = $this->seedMessageReport();
        $admin  = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->from('/admin/message-reports')
            ->post('/admin/message-reports/'.$report->id.'/review', ['action' => 'dismiss'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $fresh = $report->fresh();
        $this->assertSame('dismissed', $fresh->status);
        $this->assertSame($admin->id, $fresh->reviewed_by);
    }

    /**
     * Seeds a flagged user: risk fields on the user itself plus the queue row and
     * one signal event. `FraudReview`/`FraudEvent` have no factories, so both are
     * built with create(); `User::factory()` works.
     */
    private function seedFraudCase(array $reviewOverrides = []): User
    {
        $user = User::factory()->create([
            'name'       => 'Fahim Flagged',
            'email'      => 'fahim.flagged@example.test',
            'risk_score' => 80,
            'risk_flags' => ['duplicate_nid_hash', 'self_purchase_attempt'],
        ]);

        FraudEvent::create([
            'user_id'      => $user->id,
            'signal'       => 'duplicate_nid_hash',
            'score_impact' => 50,
            'context'      => '{}',
            'ip_address'   => '203.0.113.9',
        ]);

        FraudReview::create(array_merge([
            'user_id'    => $user->id,
            'status'     => 'escalated',
            'risk_score' => 80,
            'risk_flags' => ['duplicate_nid_hash', 'self_purchase_attempt'],
        ], $reviewOverrides));

        return $user;
    }

    /**
     * Admin/Fraud/Index.vue reads a whitelisted `reviews` paginator (snake_case
     * signal names humanised server-side), the echoed `filters.status` (active tab,
     * defaulting to 'pending') and a `tabs` option list. Sorted by risk_score desc.
     */
    public function test_admin_fraud_index_renders_the_tabbed_queue(): void
    {
        $this->seedFraudCase(['status' => 'pending']);

        $this->actingAs($this->makeSuperAdmin())
            ->get('/admin/fraud')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Fraud/Index')
                ->where('filters.status', 'pending')
                ->has('tabs', 6)
                ->has('tabs.0', fn (Assert $t) => $t
                    ->where('value', 'pending')
                    ->where('label', 'Pending')
                )
                ->has('reviews.data', 1)
                ->has('reviews.data.0', fn (Assert $r) => $r
                    ->where('user_name', 'Fahim Flagged')
                    ->where('user_email', 'fahim.flagged@example.test')
                    ->where('risk_score', 80)
                    ->where('status', 'pending')
                    ->where('reviewer', null)
                    // Underscores become spaces for display.
                    ->where('flags', ['duplicate nid hash', 'self purchase attempt'])
                    ->etc()
                )
            );
    }

    /** Both index and show authorize `fraud.view`; a bare admin 403s. */
    public function test_admin_fraud_requires_the_view_permission(): void
    {
        $user = $this->seedFraudCase();

        $this->actingAs($this->makeAdmin())
            ->get('/admin/fraud')
            ->assertForbidden();

        $this->actingAs($this->makeAdmin())
            ->get('/admin/fraud/'.$user->id)
            ->assertForbidden();
    }

    /** The queue filter follows ?status=; an escalated case hides under ?status=pending. */
    public function test_admin_fraud_index_filters_by_status(): void
    {
        $this->seedFraudCase(); // escalated
        $this->actingAs($this->makeSuperAdmin());

        $this->get('/admin/fraud')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', 'pending')
                ->has('reviews.data', 0)
            );

        $this->get('/admin/fraud?status=escalated')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', 'escalated')
                ->has('reviews.data', 1)
            );
    }

    /** Admin/Fraud/Show.vue reads `user`, an `events` list and a nullable `review`. */
    public function test_admin_fraud_show_renders_the_case(): void
    {
        $user = $this->seedFraudCase();

        $this->actingAs($this->makeSuperAdmin())
            ->get('/admin/fraud/'.$user->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Fraud/Show')
                ->where('user.name', 'Fahim Flagged')
                ->where('user.risk_score', 80)
                ->where('user.flags', ['duplicate nid hash', 'self purchase attempt'])
                ->where('user.status', 'active')
                ->has('events', 1)
                ->has('events.0', fn (Assert $e) => $e
                    ->where('signal', 'duplicate nid hash')
                    ->where('score_impact', 50)
                    ->where('ip', '203.0.113.9')
                    ->etc()
                )
                ->has('review', fn (Assert $r) => $r
                    ->where('status', 'escalated')
                    ->etc()
                )
                ->etc()
            );
    }

    /**
     * FraudService::clear is pure DB (no notifications), so this is a real
     * round-trip: the user's score and flags reset and the queue row flips to
     * 'cleared' with the reviewer recorded. Authorizes `fraud.manage`.
     *
     * This also guards the User::$fillable/$casts fix — `risk_score`/`risk_flags`
     * were missing from both, so the service's update() was silently discarded.
     */
    public function test_admin_can_clear_a_fraud_case(): void
    {
        $user  = $this->seedFraudCase();
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->from('/admin/fraud/'.$user->id)
            ->post('/admin/fraud/'.$user->id.'/clear', ['admin_notes' => 'Verified with the seller by phone — false positive.'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $fresh = $user->fresh();
        $this->assertSame(0, $fresh->risk_score);
        $this->assertSame([], $fresh->risk_flags);

        $review = FraudReview::where('user_id', $user->id)->first();
        $this->assertSame('cleared', $review->status);
        $this->assertSame($admin->id, $review->reviewed_by);
        $this->assertNotNull($review->reviewed_at);
    }

    /** restrict() flags the queue row only — the account status is untouched. */
    public function test_admin_can_restrict_a_fraud_case(): void
    {
        $user  = $this->seedFraudCase();
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->from('/admin/fraud/'.$user->id)
            ->post('/admin/fraud/'.$user->id.'/restrict', ['reason' => 'Duplicate NID across three accounts.'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $review = FraudReview::where('user_id', $user->id)->first();
        $this->assertSame('restricted', $review->status);
        $this->assertSame('Duplicate NID across three accounts.', $review->admin_notes);
        $this->assertSame($admin->id, $review->reviewed_by);

        // The user's own risk score is advisory and left alone by restrict().
        $this->assertSame(80, $user->fresh()->risk_score);
    }

    /** The Vue forms guard these client-side; the server still rejects an empty note. */
    public function test_fraud_clear_requires_a_note(): void
    {
        $user = $this->seedFraudCase();

        $this->actingAs($this->makeSuperAdmin())
            ->from('/admin/fraud/'.$user->id)
            ->post('/admin/fraud/'.$user->id.'/clear', ['admin_notes' => ''])
            ->assertSessionHasErrors('admin_notes');

        $this->assertSame(80, $user->fresh()->risk_score);
    }

    // ── Admin support tickets (checkpoint 38) ───────────────────────────

    /**
     * `SupportTicket` imports HasFactory without applying it (see the
     * pre-existing-issues note), so tickets are built with create().
     *
     * The owner's email and the reference are unique per call — a test that
     * seeds two tickets would otherwise trip the users.email unique index.
     */
    private int $ticketSeq = 0;

    private function seedAdminTicket(array $overrides = []): SupportTicket
    {
        $suffix = ++$this->ticketSeq === 1 ? '' : (string) $this->ticketSeq;
        $owner  = User::factory()->create([
            'name'  => 'Tanvir Buyer',
            'email' => "tanvir.buyer{$suffix}@example.test",
        ]);

        return SupportTicket::create(array_merge([
            'reference'     => 'TKT-20260818-CCC333'.$suffix,
            'user_id'       => $owner->id,
            'category'      => 'payment',
            'subject'       => 'Payment stuck on checkout',
            'priority'      => 'high',
            'status'        => TicketStatus::Open,
            'last_reply_at' => now(),
        ], $overrides));
    }

    /**
     * Admin/Tickets/Index.vue reads a whitelisted `tickets` paginator, the echoed
     * `filters` (status tab + q + priority), a `tabs` list and `priorities`.
     * index() authorizes `tickets.manage`, so it needs makeSuperAdmin().
     */
    public function test_admin_tickets_index_renders_the_tabbed_list(): void
    {
        $this->seedAdminTicket();

        $this->actingAs($this->makeSuperAdmin())
            ->get('/admin/tickets')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Tickets/Index')
                ->where('filters.status', 'open')
                ->where('filters.q', null)
                ->where('filters.priority', null)
                // 7 tabs: the Blade's 6 plus waiting_for_staff.
                ->has('tabs', 7)
                ->has('priorities', 4)
                ->has('tickets.data', 1)
                ->has('tickets.data.0', fn (Assert $t) => $t
                    ->where('reference', 'TKT-20260818-CCC333')
                    ->where('user_name', 'Tanvir Buyer')
                    ->where('subject', 'Payment stuck on checkout')
                    ->where('priority_label', 'High')
                    ->where('priority_color', 'amber')
                    ->where('status', 'open')
                    ->where('assignee', null)
                    ->etc()
                )
            );
    }

    /** The queue includes a waiting_for_staff tab — the state a user reply produces. */
    public function test_admin_tickets_index_has_a_waiting_on_staff_tab(): void
    {
        $this->seedAdminTicket(['status' => TicketStatus::WaitingForStaff]);

        $this->actingAs($this->makeSuperAdmin())
            ->get('/admin/tickets?status=waiting_for_staff')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.status', 'waiting_for_staff')
                ->has('tickets.data', 1)
            );
    }

    /** Reads authorize `tickets.view`; a permissionless admin role still 403s. */
    public function test_admin_tickets_require_a_ticket_permission(): void
    {
        $ticket = $this->seedAdminTicket();

        $this->actingAs($this->makeAdmin())
            ->get('/admin/tickets')
            ->assertForbidden();

        $this->actingAs($this->makeAdmin())
            ->get('/admin/tickets/'.$ticket->id)
            ->assertForbidden();
    }

    /**
     * The sidebar shows Support Tickets on `tickets.view`, which the seeded
     * moderator role holds without `tickets.manage` — so the queue must open for
     * them while every write stays behind manage. Authorizing manage on index()
     * made a moderator's own menu link 403.
     */
    public function test_ticket_view_permission_reads_but_cannot_write(): void
    {
        $ticket = $this->seedAdminTicket();

        $moderator = User::factory()->create();
        $moderator->roles()->attach(Role::where('name', 'moderator')->value('id'));
        $this->assertTrue($moderator->hasPermission('tickets.view'));
        $this->assertFalse($moderator->hasPermission('tickets.manage'));

        $this->actingAs($moderator);
        $this->get('/admin/tickets')->assertOk();
        $this->get('/admin/tickets/'.$ticket->id)->assertOk();

        $this->post('/admin/tickets/'.$ticket->id.'/reply', ['body' => 'Nope.'])->assertForbidden();
        $this->post('/admin/tickets/'.$ticket->id.'/note', ['body' => 'Nope.'])->assertForbidden();
        $this->post('/admin/tickets/'.$ticket->id.'/assign', ['assigned_to' => $moderator->id])->assertForbidden();
        $this->patch('/admin/tickets/'.$ticket->id.'/status', ['status' => 'closed'])->assertForbidden();
        $this->patch('/admin/tickets/'.$ticket->id.'/priority', ['priority' => 'low'])->assertForbidden();

        $this->assertSame(0, $ticket->messages()->count());
        $this->assertSame(TicketStatus::Open, $ticket->fresh()->status);
    }

    /**
     * Regression guard for the ungrouped orWhere in the Blade-era query: the
     * reference/subject search was OR'd at the top level, so a match escaped the
     * status and priority filters entirely. The search is now wrapped.
     */
    public function test_admin_tickets_search_stays_inside_the_status_filter(): void
    {
        $this->seedAdminTicket(['subject' => 'Refund not received']);
        $this->seedAdminTicket([
            'reference' => 'TKT-20260818-DDD444',
            'subject'   => 'Refund question, already closed',
            'status'    => TicketStatus::Closed,
        ]);

        $this->actingAs($this->makeSuperAdmin());

        // Searching inside the Open tab must not surface the closed ticket.
        $this->get('/admin/tickets?status=open&q=Refund')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.q', 'Refund')
                ->has('tickets.data', 1)
                ->where('tickets.data.0.subject', 'Refund not received')
            );

        // ?status=all sees both.
        $this->get('/admin/tickets?status=all&q=Refund')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('tickets.data', 2));
    }

    /** The priority select filters too, and is echoed back for the control. */
    public function test_admin_tickets_index_filters_by_priority(): void
    {
        $this->seedAdminTicket(); // high
        $this->actingAs($this->makeSuperAdmin());

        $this->get('/admin/tickets?priority=low')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.priority', 'low')
                ->has('tickets.data', 0)
            );

        $this->get('/admin/tickets?priority=high')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('tickets.data', 1));
    }

    /**
     * Admin/Tickets/Show.vue reads the ticket, its thread, the assignable `staff`
     * list and the status/priority option lists. Admins DO see internal notes
     * (flagged is_internal so the bubble renders amber + an INTERNAL badge).
     */
    public function test_admin_tickets_show_renders_the_thread(): void
    {
        $ticket = $this->seedAdminTicket();
        $admin  = $this->makeSuperAdmin();
        $ticket->messages()->create([
            'user_id'        => $ticket->user_id,
            'body'           => 'My card was charged but the order is still pending.',
            'is_staff_reply' => false,
        ]);
        $ticket->messages()->create([
            'user_id'          => $admin->id,
            'body'             => 'Escalating to finance — gateway shows a hold.',
            'is_staff_reply'   => true,
            'is_internal_note' => true,
        ]);

        $this->actingAs($admin)
            ->get('/admin/tickets/'.$ticket->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Tickets/Show')
                ->where('ticket.reference', 'TKT-20260818-CCC333')
                ->where('ticket.user_email', 'tanvir.buyer@example.test')
                ->where('ticket.priority_color', 'amber')
                ->where('ticket.assigned_to', null)
                ->has('ticket.messages', 2)
                ->where('ticket.messages.0.is_staff', false)
                ->where('ticket.messages.0.initial', 'T')
                ->where('ticket.messages.1.is_internal', true)
                ->where('ticket.messages.1.attachment', null)
                ->has('statuses', 5)
                ->has('priorities', 4)
                ->has('staff')
                ->etc()
            );
    }

    /**
     * reply() is DB + an in-app (database) notification — no SMS/Telegram — so
     * this is a real round-trip: the message lands as a staff reply and the
     * service flips the ticket to waiting_for_user.
     */
    public function test_admin_can_reply_to_a_ticket(): void
    {
        $ticket = $this->seedAdminTicket();
        $admin  = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->from('/admin/tickets/'.$ticket->id)
            ->post('/admin/tickets/'.$ticket->id.'/reply', ['body' => 'We have released the hold — please retry.'])
            ->assertRedirect()
            ->assertSessionHas('success');

        // messages() is ->oldest(); reorder so "the message just written" is
        // unambiguous even when two rows share a created_at second.
        $msg = $ticket->messages()->reorder('id', 'desc')->first();
        $this->assertSame('We have released the hold — please retry.', $msg->body);
        $this->assertTrue($msg->is_staff_reply);
        $this->assertFalse((bool) $msg->is_internal_note);
        $this->assertSame(TicketStatus::WaitingForUser, $ticket->fresh()->status);
    }

    /** Internal notes are staff-only rows on the same thread. */
    public function test_admin_can_add_an_internal_note(): void
    {
        $ticket = $this->seedAdminTicket();

        $this->actingAs($this->makeSuperAdmin())
            ->from('/admin/tickets/'.$ticket->id)
            ->post('/admin/tickets/'.$ticket->id.'/note', ['body' => 'Chargeback risk — watch this account.'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $msg = $ticket->messages()->reorder('id', 'desc')->first();
        $this->assertTrue((bool) $msg->is_internal_note);
        $this->assertTrue((bool) $msg->is_staff_reply);
        // A note is not a reply: the status must not move to waiting_for_user.
        $this->assertSame(TicketStatus::Open, $ticket->fresh()->status);
    }

    /** assign() is a POST; passing an empty assigned_to unassigns. */
    public function test_admin_can_assign_and_unassign_a_ticket(): void
    {
        $ticket = $this->seedAdminTicket();
        $admin  = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->from('/admin/tickets/'.$ticket->id)
            ->post('/admin/tickets/'.$ticket->id.'/assign', ['assigned_to' => $admin->id])
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertSame($admin->id, $ticket->fresh()->assigned_to);

        $this->post('/admin/tickets/'.$ticket->id.'/assign', ['assigned_to' => ''])
            ->assertRedirect();
        $this->assertNull($ticket->fresh()->assigned_to);
    }

    /** status and priority are PATCH routes, so the Vue forms use router.patch. */
    public function test_admin_can_change_ticket_status_and_priority(): void
    {
        $ticket = $this->seedAdminTicket();
        $this->actingAs($this->makeSuperAdmin());

        $this->from('/admin/tickets/'.$ticket->id)
            ->patch('/admin/tickets/'.$ticket->id.'/status', ['status' => 'resolved', 'reason' => 'Refund issued.'])
            ->assertRedirect()
            ->assertSessionHas('success');
        $fresh = $ticket->fresh();
        $this->assertSame(TicketStatus::Resolved, $fresh->status);
        $this->assertNotNull($fresh->resolved_at);

        $this->patch('/admin/tickets/'.$ticket->id.'/priority', ['priority' => 'urgent'])
            ->assertRedirect();
        $this->assertSame('urgent', $ticket->fresh()->priority);
    }

    /**
     * Security regression guard: Admin\TicketController::internalNote writes
     * is_internal_note=true rows, and Dashboard\TicketController::show used to
     * load the whole thread — leaking staff-only notes to the ticket owner.
     */
    public function test_dashboard_ticket_thread_hides_internal_notes(): void
    {
        $ticket = $this->seedAdminTicket();
        $owner  = $ticket->user;

        $ticket->messages()->create([
            'user_id'        => $owner->id,
            'body'           => 'My card was charged but nothing happened.',
            'is_staff_reply' => false,
        ]);
        $ticket->messages()->create([
            'user_id'          => $this->makeSuperAdmin()->id,
            'body'             => 'Internal: possible chargeback fraud, do not refund yet.',
            'is_staff_reply'   => true,
            'is_internal_note' => true,
        ]);

        $this->actingAs($owner)
            ->get('/dashboard/tickets/'.$ticket->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Tickets/Show')
                ->has('ticket.messages', 1)
                ->where('ticket.messages.0.is_staff', false)
            )
            ->assertDontSee('possible chargeback fraud', false);
    }

    // ── Admin roles + permissions (checkpoint 39) ───────────────────────

    /**
     * Admin/Roles/Index.vue reads a plain `roles` list (no paginator — roles are
     * few), each with its member count, sorted permission names and an
     * `edit_url` that is null for a protected role. index() authorizes
     * `roles.view`; the seeder creates 5 roles, ordered by id.
     */
    public function test_admin_roles_index_renders_the_role_cards(): void
    {
        $this->actingAs($this->makeSuperAdmin())
            ->get('/admin/roles')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Roles/Index')
                ->has('roles', 5)
                ->has('roles.0', fn (Assert $r) => $r
                    ->where('name', 'super_admin')
                    ->where('display_name', 'Super Admin')
                    ->where('is_protected', true)
                    ->where('users_count', 0)
                    // No edit page for a protected role — edit() aborts 403.
                    ->where('edit_url', null)
                    ->etc()
                )
                ->has('roles.1', fn (Assert $r) => $r
                    ->where('name', 'admin')
                    ->where('is_protected', false)
                    // The acting super-admin holds this role.
                    ->where('users_count', 1)
                    ->etc()
                )
                ->where('roles.1.edit_url', route('admin.roles.edit', Role::where('name', 'admin')->value('id')))
            );
    }

    /** Every role route authorizes a roles.* permission; a bare admin 403s. */
    public function test_admin_roles_require_the_role_permissions(): void
    {
        $role = Role::where('name', 'moderator')->first();

        $this->actingAs($this->makeAdmin())
            ->get('/admin/roles')
            ->assertForbidden();

        $this->actingAs($this->makeAdmin())
            ->get('/admin/roles/'.$role->id.'/edit')
            ->assertForbidden();

        $this->actingAs($this->makeAdmin())
            ->post('/admin/roles', ['name' => 'nope', 'display_name' => 'Nope'])
            ->assertForbidden();
    }

    /**
     * Regression guard for `Role::$fillable`: it omitted `description`, so the
     * create form's Description field was silently discarded on every write.
     */
    public function test_admin_roles_store_persists_the_description(): void
    {
        $this->actingAs($this->makeSuperAdmin())
            ->post('/admin/roles', [
                'name'         => 'content_manager',
                'display_name' => 'Content Manager',
                'description'  => 'Edits listings and categories, no finance.',
            ])
            ->assertSessionHas('success');

        $role = Role::where('name', 'content_manager')->first();
        $this->assertNotNull($role);
        $this->assertSame('Edits listings and categories, no finance.', $role->description);
        $this->assertTrue($role->is_admin_role);
        $this->assertFalse($role->is_protected);
        // store() sends staff to the edit page to assign permissions.
        $this->assertSame(0, $role->permissions()->count());
    }

    /** The slug is `regex:/^[a-z_]+$/` — the Vue form surfaces form.errors.name. */
    public function test_admin_roles_store_rejects_a_non_slug_name(): void
    {
        $this->actingAs($this->makeSuperAdmin())
            ->from('/admin/roles')
            ->post('/admin/roles', ['name' => 'Content Manager 2', 'display_name' => 'Content Manager'])
            ->assertSessionHasErrors('name');

        $this->assertSame(5, Role::count());
    }

    /**
     * Admin/Roles/Edit.vue reads the role, the full permission matrix grouped by
     * `permissions.group`, and the granted ids as `assigned`.
     */
    public function test_admin_roles_edit_renders_the_permission_matrix(): void
    {
        $role   = Role::where('name', 'moderator')->first();
        $perms  = $role->permissions->pluck('id')->all();

        $this->actingAs($this->makeSuperAdmin())
            ->get('/admin/roles/'.$role->id.'/edit')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Roles/Edit')
                ->where('role.name', 'moderator')
                ->where('role.display_name', 'Moderator')
                ->has('groups', 21)
                ->has('groups.0', fn (Assert $g) => $g
                    ->where('group', 'audit')
                    ->where('label', 'Audit')
                    ->has('permissions', 1)
                    ->etc()
                )
                ->where('assigned', $perms)
            );
    }

    /** update() syncs permissions and audits the change. */
    public function test_admin_roles_update_syncs_permissions_and_description(): void
    {
        $role = Role::create([
            'name'          => 'content_manager',
            'display_name'  => 'Content Manager',
            'is_admin_role' => true,
        ]);
        $keep = Permission::where('name', 'listings.view')->value('id');
        $add  = Permission::where('name', 'categories.manage')->value('id');
        $drop = Permission::where('name', 'users.delete')->value('id');
        $role->permissions()->sync([$keep, $drop]);

        $this->actingAs($this->makeSuperAdmin())
            ->patch('/admin/roles/'.$role->id, [
                'display_name' => 'Content Lead',
                'description'  => 'Now also owns categories.',
                'permissions'  => [$keep, $add],
            ])
            ->assertRedirect(route('admin.roles'))
            ->assertSessionHas('success');

        $role->refresh();
        $this->assertSame('Content Lead', $role->display_name);
        // Same fillable regression as store(): description used to be dropped.
        $this->assertSame('Now also owns categories.', $role->description);
        $this->assertEqualsCanonicalizing([$keep, $add], $role->permissions->pluck('id')->all());
    }

    /**
     * The seeder marks `super_admin` protected, and both edit() and update()
     * abort 403 on a protected role. `is_protected` was missing from
     * Role::$fillable, so the flag never persisted and the guard was dead code —
     * anyone with the roles permission could rewrite super_admin's permissions.
     */
    public function test_protected_roles_cannot_be_edited(): void
    {
        $role = Role::where('name', 'super_admin')->first();
        $this->assertTrue($role->is_protected);

        $this->actingAs($this->makeSuperAdmin());
        $this->get('/admin/roles/'.$role->id.'/edit')->assertForbidden();
        $this->patch('/admin/roles/'.$role->id, [
            'display_name' => 'Hijacked',
            'permissions'  => [],
        ])->assertForbidden();

        $this->assertSame('Super Admin', $role->fresh()->display_name);
        $this->assertGreaterThan(0, $role->permissions()->count());
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
