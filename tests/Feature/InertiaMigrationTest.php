<?php
namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Category;
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
}
