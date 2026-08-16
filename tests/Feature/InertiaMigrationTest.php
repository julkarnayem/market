<?php
namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Category;
use App\Models\Favorite;
use App\Models\User;
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

    /** Coexistence guard: /marketplace is still Blade and must not 500 or gain an Inertia payload. */
    public function test_unmigrated_blade_pages_still_render(): void
    {
        // / and /contact are Inertia now; /marketplace is the remaining public Blade page.
        $this->get('/marketplace')
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
}
