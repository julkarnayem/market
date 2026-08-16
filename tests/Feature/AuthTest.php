<?php
namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_user_can_register(): void
    {
        $response = $this->post('/register', [
            'name'                  => 'Test User',
            'username'              => 'testuser123',
            'email'                 => 'test@example.com',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);
        $response->assertRedirect();
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create(['password' => bcrypt('Password123!')]);
        $response = $this->post('/login', ['email' => $user->email, 'password' => 'Password123!']);
        $response->assertRedirect();
        $this->assertAuthenticated();
    }

    public function test_wrong_password_fails(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct')]);
        $response = $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        $this->assertGuest();
    }

    public function test_suspended_user_is_blocked(): void
    {
        $user = User::factory()->create(['status' => 'suspended', 'password' => bcrypt('Password123!')]);
        $this->post('/login', ['email' => $user->email, 'password' => 'Password123!']);
        // Suspended users are logged out by EnsureUserIsActive middleware
        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_normal_user_cannot_access_admin(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/admin');
        $response->assertForbidden();
    }

    public function test_logout_clears_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/logout');
        $this->assertGuest();
    }
}
