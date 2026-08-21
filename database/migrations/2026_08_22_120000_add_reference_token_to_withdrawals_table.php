<?php

use App\Models\Withdrawal;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            // The random half of the public reference, WD-{id}{TOKEN}. The id in
            // front already makes the reference unique, so this is style, not a
            // key — no unique index or collision check needed.
            $table->string('reference_token', 16)->nullable()->after('id');
        });

        // Backfill existing rows so reference() is stable for them right away.
        // Query builder, not the model, so this doesn't bump updated_at.
        foreach (DB::table('withdrawals')->whereNull('reference_token')->pluck('id') as $id) {
            DB::table('withdrawals')
                ->where('id', $id)
                ->update(['reference_token' => Withdrawal::generateReferenceToken()]);
        }
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropColumn('reference_token');
        });
    }
};
