<?php
namespace Tests\Feature;

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
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

    /** Coexistence guard: these are still Blade and must not 500. */
    public function test_unmigrated_blade_pages_still_render(): void
    {
        $this->get('/')->assertOk();
        $this->get('/marketplace')->assertOk();
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
}
