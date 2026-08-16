<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        // LOCKED defaults. Money = poisha (int). Rates = basis points (int).
        $defaults = [
            ['key' => 'site_name', 'value' => 'Digital Asset Marketplace', 'type' => 'string', 'group' => 'general'],
            ['key' => 'currency', 'value' => 'BDT', 'type' => 'string', 'group' => 'general'],
            ['key' => 'seller_fee_bp', 'value' => '1000', 'type' => 'int', 'group' => 'fees'],          // 10%
            ['key' => 'buyer_fee_enabled', 'value' => '0', 'type' => 'bool', 'group' => 'fees'],
            ['key' => 'buyer_fee_type', 'value' => 'percentage', 'type' => 'string', 'group' => 'fees'],
            ['key' => 'buyer_fee_value', 'value' => '0', 'type' => 'int', 'group' => 'fees'],
            ['key' => 'minimum_withdrawal', 'value' => '5000', 'type' => 'int', 'group' => 'withdrawal'], // ৳50
            ['key' => 'withdrawal_fee', 'value' => '500', 'type' => 'int', 'group' => 'withdrawal'],      // ৳5
            ['key' => 'offer_validity_hours', 'value' => '8', 'type' => 'int', 'group' => 'offers'],
            ['key' => 'buyer_protection_hours', 'value' => '72', 'type' => 'int', 'group' => 'orders'],
            ['key' => 'earning_lock_hours', 'value' => '8', 'type' => 'int', 'group' => 'orders'],
            ['key' => 'promotion_prices', 'value' => json_encode([1 => 5000, 2 => 10000, 3 => 15000, 4 => 20000, 5 => 25000]), 'type' => 'json', 'group' => 'promotion'],
            ['key' => 'maintenance_mode', 'value' => '0', 'type' => 'bool', 'group' => 'general'],
        ];

        foreach ($defaults as $row) {
            Setting::firstOrCreate(['key' => $row['key']], $row);
        }
    }
}
