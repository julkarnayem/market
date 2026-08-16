<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionRoleSeeder::class,
            SettingsSeeder::class,
            CategorySeeder::class,
        ]);

        // One super-admin account. Change the password immediately after first login.
        $admin = User::firstOrCreate(
            ['email' => 'admin@marketplace.test'],
            [
                'name' => 'Platform Admin',
                'username' => 'admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'status' => 'active',
            ]
        );
        $admin->roles()->syncWithoutDetaching(Role::where('name', 'admin')->pluck('id'));
        Wallet::firstOrCreate(['user_id' => $admin->id], ['currency' => 'BDT']);
    }
}
