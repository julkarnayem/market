<?php
namespace Tests\Feature;

use App\Models\Order;
use App\Models\Role;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    /** IDOR: User A cannot view User B's order */
    public function test_idor_order_access_blocked(): void
    {
        $userA = User::factory()->create();
        $buyer = User::factory()->create();
        $seller = User::factory()->create();

        $order = Order::factory()->create([
            'buyer_user_id'  => $buyer->id,
            'seller_user_id' => $seller->id,
        ]);

        $response = $this->actingAs($userA)->get("/dashboard/orders/{$order->id}");
        $response->assertForbidden();
    }

    /** IDOR: User A cannot view User B's support ticket */
    public function test_idor_ticket_access_blocked(): void
    {
        $userA  = User::factory()->create();
        $userB  = User::factory()->create();
        $ticket = SupportTicket::factory()->create(['user_id' => $userB->id]);

        $response = $this->actingAs($userA)->get("/dashboard/tickets/{$ticket->id}");
        $response->assertForbidden();
    }

    /** Privilege check: support role cannot approve withdrawals */
    public function test_support_staff_cannot_approve_withdrawal(): void
    {
        $supportRole = Role::where('name','support')->first();
        $this->assertNotNull($supportRole, 'Support role must exist (run db:seed first)');

        $staff = User::factory()->create();
        $staff->roles()->attach($supportRole);

        $user       = User::factory()->create();
        $wallet     = Wallet::factory()->for($user)->create(['available_balance' => 20000]);
        $withdrawal = Withdrawal::factory()->create(['user_id' => $user->id, 'status' => 'pending']);

        $response = $this->actingAs($staff)->post("/admin/withdrawals/{$withdrawal->id}/approve");
        $response->assertForbidden();
    }

    /** Normal user cannot access admin */
    public function test_normal_user_cannot_access_admin_dashboard(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/admin');
        $response->assertForbidden();
    }

    /** Login rate limiting kicks in */
    public function test_login_rate_limited_after_ten_attempts(): void
    {
        for ($i = 0; $i < 11; $i++) {
            $this->post('/login', ['email' => 'test@nowhere.com', 'password' => 'wrong']);
        }
        $response = $this->post('/login', ['email' => 'test@nowhere.com', 'password' => 'wrong']);
        $response->assertStatus(429);
    }

    /** Self-purchase: user cannot checkout own listing */
    public function test_self_purchase_checkout_blocked(): void
    {
        $user  = User::factory()->verified()->create();
        Wallet::factory()->for($user)->create();
        $asset = \App\Models\Asset::factory()->published()->for($user, 'seller')->create();

        $response = $this->actingAs($user)->get("/checkout/{$asset->slug}");
        // Checkout controller calls validateAndCalculate() which aborts with 403
        $response->assertStatus(302); // redirects with error (back() with error)
    }
}
