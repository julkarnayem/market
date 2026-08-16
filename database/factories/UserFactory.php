<?php
namespace Database\Factories;

use App\Enums\UserStatus;
use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'                  => fake()->name(),
            'username'              => fake()->unique()->userName(),
            'email'                 => fake()->unique()->safeEmail(),
            'phone'                 => null,
            'password'              => Hash::make('Password123!'),
            'bio'                   => null,
            'email_verified_at'     => now(),
            'status'                => UserStatus::Active,
            'verification_status'   => VerificationStatus::NotSubmitted,
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => UserStatus::Suspended]);
    }

    public function verified(): static
    {
        return $this->state(fn () => [
            'verification_status' => VerificationStatus::Approved,
        ]);
    }

    public function admin(): static
    {
        return $this->afterCreating(function ($user) {
            $role = \App\Models\Role::where('name','admin')->first();
            if ($role) $user->roles()->attach($role);
        });
    }

    public function withWallet(): static
    {
        return $this->afterCreating(function ($user) {
            \App\Models\Wallet::factory()->for($user)->create();
        });
    }
}
