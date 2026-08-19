<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inventory type on listings, plus the accepted-bid pointer.
 *
 * `inventory_type` decides which purchase actions a listing offers:
 *   single    — one unique item; the only type that can be bid on
 *   multiple  — a finite stock that counts down to zero
 *   unlimited — sells forever and never draws down stock
 *
 * Existing rows are classified from their quantity so nothing changes
 * behaviour on deploy: quantity > 1 becomes multiple, everything else single.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->string('inventory_type', 20)->default('single')->after('price');
            $table->foreignId('accepted_bid_id')->nullable()->after('sold_quantity');
            $table->index(['inventory_type', 'status']);
        });

        DB::table('assets')->where('quantity', '>', 1)->update(['inventory_type' => 'multiple']);
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropIndex(['inventory_type', 'status']);
            $table->dropColumn(['inventory_type', 'accepted_bid_id']);
        });
    }
};
