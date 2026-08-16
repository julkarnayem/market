<?php
namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateSuperAdminCommand extends Command
{
    protected $signature   = 'admin:create-super {--name=} {--email=} {--password=}';
    protected $description = 'Create the initial Super Admin account.';

    public function handle(): void
    {
        $name     = $this->option('name')     ?: $this->ask('Name');
        $email    = $this->option('email')    ?: $this->ask('Email');
        $password = $this->option('password') ?: $this->secret('Password (min 10 chars)');

        if (strlen($password) < 10) {
            $this->error('Password must be at least 10 characters.');
            return;
        }

        if (User::where('email', $email)->exists()) {
            $this->error('A user with that email already exists.');
            return;
        }

        $user = User::create([
            'name'              => $name,
            'email'             => $email,
            'password'          => Hash::make($password),
            'email_verified_at' => now(),
            'status'            => 'active',
        ]);

        $role = Role::firstOrCreate(
            ['name' => 'super_admin'],
            ['display_name' => 'Super Admin', 'is_admin_role' => true, 'is_protected' => true]
        );
        $user->roles()->attach($role);

        $this->info("✓ Super Admin created: {$user->email}");
        $this->warn("→ Change this password immediately after first login.");
    }
}
