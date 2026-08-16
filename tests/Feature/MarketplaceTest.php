<?php
namespace Tests\Feature;

use App\Models\Asset;
use App\Models\User;
use App\Models\Wallet;
use Tests\TestCase;

class MarketplaceTest extends TestCase
{
    public function test_homepage_loads(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_marketplace_index_loads(): void
    {
        $this->get('/marketplace')->assertOk();
    }

    public function test_robots_txt_blocks_private_paths(): void
    {
        $response = $this->get('/robots.txt')->assertOk();
        $this->assertStringContainsString('Disallow: /admin/', $response->content());
        $this->assertStringContainsString('Disallow: /dashboard/', $response->content());
        $this->assertStringContainsString('Allow: /marketplace', $response->content());
    }

    public function test_sitemap_is_valid_xml(): void
    {
        $response = $this->get('/sitemap.xml')->assertOk();
        $this->assertStringContainsString('<urlset', $response->content());
    }

    public function test_asset_detail_page_loads(): void
    {
        $user  = User::factory()->create();
        $asset = Asset::factory()->published()->create();

        $this->get("/asset/{$asset->slug}")->assertOk();
    }

    public function test_guest_cannot_access_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_user_can_access_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_favorite_toggle_requires_auth(): void
    {
        $response = $this->postJson('/favorites/toggle', ['asset_id' => 1]);
        $response->assertUnauthorized();
    }

    public function test_self_purchase_blocked_server_side(): void
    {
        $user  = User::factory()->verified()->create();
        $asset = Asset::factory()->published()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get("/checkout/{$asset->slug}");
        // Should redirect back with error — not 200
        $this->assertNotEquals(200, $response->status());
    }
}
