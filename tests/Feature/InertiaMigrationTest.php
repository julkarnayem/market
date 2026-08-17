<?php
namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Favorite;
use App\Models\PhoneOtp;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail as VerifyEmailNotification;
use Illuminate\Support\Facades\Notification;
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
     * Repoint this at a still-Blade page each time its target migrates —
     * /marketplace, /dashboard, /dashboard/favorites and /dashboard/wallet
     * have all already passed through here.
     */
    public function test_unmigrated_blade_pages_still_render(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard/promotions')
            ->assertOk()
            ->assertDontSee('data-page', false);
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
        ];
    }
}
