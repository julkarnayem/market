<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The set of payout methods, made admin-manageable. This replaces the hardcoded
 * App\Enums\WithdrawalMethod: the *set* now lives in rows an admin can add,
 * rename, reorder and switch on/off. Every method still declares a `type`
 * ('mfs' | 'bank') because the withdrawals table only stores two field-shapes —
 * a mobile number, or the bank fields — so a method is always one of those two.
 *
 * The five original methods are seeded here (not in a seeder) so a fresh test
 * database — which only runs PermissionRoleSeeder — still has them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawal_methods', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();          // slug, e.g. bkash — immutable
            $table->string('label');                  // display name, e.g. bKash — editable
            $table->string('type')->default('mfs');   // 'mfs' | 'bank' — the field-shape
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        $defaults = [
            ['key' => 'bkash',  'label' => 'bKash',         'type' => 'mfs',  'sort_order' => 1],
            ['key' => 'nagad',  'label' => 'Nagad',         'type' => 'mfs',  'sort_order' => 2],
            ['key' => 'rocket', 'label' => 'Rocket',        'type' => 'mfs',  'sort_order' => 3],
            ['key' => 'upay',   'label' => 'Upay',          'type' => 'mfs',  'sort_order' => 4],
            ['key' => 'bank',   'label' => 'Bank transfer', 'type' => 'bank', 'sort_order' => 5],
        ];

        foreach ($defaults as $row) {
            // Guarded so re-running the migration (or a partial state) never
            // duplicates a key.
            DB::table('withdrawal_methods')->updateOrInsert(
                ['key' => $row['key']],
                $row + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawal_methods');
    }
};
