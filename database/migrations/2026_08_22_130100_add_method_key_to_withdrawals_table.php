<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Record exactly which withdrawal method each payout used. Historically the
 * method was inferred from (`method`, `mfs_provider`): mfs rows carried the
 * provider slug in mfs_provider, bank rows carried nothing. That is ambiguous
 * the moment a second bank-type method exists, and it loses the label if a
 * method is later renamed or removed. `method_key` pins the row to a
 * withdrawal_methods.key so labels stay correct regardless.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->string('method_key')->nullable()->after('mfs_provider');
        });

        // Backfill: mfs rows already name their provider; bank rows map to 'bank'.
        DB::table('withdrawals')->where('method', 'bank')->whereNull('method_key')
            ->update(['method_key' => 'bank']);
        DB::table('withdrawals')->where('method', '!=', 'bank')->whereNull('method_key')
            ->update(['method_key' => DB::raw('mfs_provider')]);
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropColumn('method_key');
        });
    }
};
