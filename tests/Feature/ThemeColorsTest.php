<?php
namespace Tests\Feature;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\ThemeColors;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Admin-editable theme colors (Admin → Settings).
 *
 * The 4 brand-semantic scales resolve through CSS variables (defaults in
 * app.css); customizing a role stores its base hex, ThemeColors regenerates the
 * 50–900 scale, and AppServiceProvider injects the overrides into the root Blade
 * as a <style>. These lock in the pipeline end-to-end plus the two rules that
 * keep it safe: a genuine pick is stored (and reaches the page HTML), while the
 * default hex / blank / Reset drops the override so the exact default returns.
 */
class ThemeColorsTest extends TestCase
{
    /** Staff holding the seeded admin role, which Gate::before grants fully. */
    private function admin(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', 'admin')->value('id'));

        return $user->fresh();
    }

    public function test_an_admin_can_customize_a_color_and_it_is_stored(): void
    {
        $this->actingAs($this->admin())
            ->patch(route('admin.settings.theme.update'), [
                'brand'    => '#ff0000',
                'money'    => ThemeColors::DEFAULTS['money'],   // unchanged → forgotten
                'featured' => ThemeColors::DEFAULTS['featured'],
                'danger'   => ThemeColors::DEFAULTS['danger'],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        // Only the genuinely-changed role is persisted (normalized to upper case).
        $this->assertSame('#FF0000', Setting::query()->where('key', 'theme_brand')->value('value'));
        $this->assertNull(Setting::query()->where('key', 'theme_money')->first());

        // The generator emits the customized role's shades, and only that role.
        $css = app(ThemeColors::class)->overridesCss();
        $this->assertStringContainsString('--c-brand-600:', $css);
        $this->assertStringNotContainsString('--c-mint-', $css);
    }

    public function test_the_override_reaches_the_page_html_as_a_style_block(): void
    {
        // No customization → no <style> is emitted.
        $this->get('/faq')->assertOk()->assertDontSee('theme-overrides');

        $this->actingAs($this->admin())->patch(route('admin.settings.theme.update'), [
            'brand' => '#0EA5E9',
        ]);

        // Any Inertia page (same root Blade) now carries the injected overrides.
        $this->get('/faq')
            ->assertOk()
            ->assertSee('id="theme-overrides"', false)
            ->assertSee('--c-brand-600:', false);
    }

    public function test_an_invalid_hex_is_rejected(): void
    {
        $this->actingAs($this->admin())
            ->patch(route('admin.settings.theme.update'), ['brand' => 'red'])
            ->assertSessionHasErrors('brand');

        $this->assertDatabaseMissing('settings', ['key' => 'theme_brand']);
    }

    public function test_resetting_to_the_default_or_blank_forgets_the_override(): void
    {
        $admin = $this->admin();

        // Customize, then send the default hex back → override is dropped.
        $this->actingAs($admin)->patch(route('admin.settings.theme.update'), ['brand' => '#123456']);
        $this->assertDatabaseHas('settings', ['key' => 'theme_brand']);

        $this->actingAs($admin)->patch(route('admin.settings.theme.update'), [
            'brand' => ThemeColors::DEFAULTS['brand'],
        ]);
        $this->assertDatabaseMissing('settings', ['key' => 'theme_brand']);

        // A blank field also clears it.
        $this->actingAs($admin)->patch(route('admin.settings.theme.update'), ['danger' => '#123456']);
        $this->assertDatabaseHas('settings', ['key' => 'theme_danger']);
        $this->actingAs($admin)->patch(route('admin.settings.theme.update'), ['danger' => '']);
        $this->assertDatabaseMissing('settings', ['key' => 'theme_danger']);

        $this->assertSame('', app(ThemeColors::class)->overridesCss());
    }

    public function test_current_falls_back_to_defaults_when_unset(): void
    {
        $this->assertSame(ThemeColors::DEFAULTS, app(ThemeColors::class)->current());
    }

    public function test_customizing_requires_the_settings_manage_permission(): void
    {
        // A moderator is an admin (passes the admin middleware) but lacks
        // settings.manage, so the controller's authorize() refuses.
        $moderator = User::factory()->create();
        $moderator->roles()->attach(Role::where('name', 'moderator')->value('id'));
        $moderator = $moderator->fresh();
        $this->assertTrue($moderator->isAdmin());
        $this->assertFalse($moderator->hasPermission('settings.manage'));

        $this->actingAs($moderator)
            ->patch(route('admin.settings.theme.update'), ['brand' => '#ff0000'])
            ->assertForbidden();

        $this->assertDatabaseMissing('settings', ['key' => 'theme_brand']);
    }
}
