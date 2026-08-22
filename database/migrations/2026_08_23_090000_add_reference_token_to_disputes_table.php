<?php

use App\Models\Dispute;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dispute references were D-{10000 + id}: a public handle that leaks the row's
 * position in the table, so anyone holding one could count up and address every
 * other dispute. Withdrawals already solved this — WD-{id}{TOKEN}, where the
 * token is a stored random suffix — and disputes now take the same shape,
 * D-{id}{TOKEN}, because the reference is what the view URL resolves on.
 *
 * Existing rows are backfilled in place. Only disputes.reference and the new
 * token column are written; orders, dispute messages, evidence and proposals go
 * on pointing at the same dispute ids they always did. Order status-history
 * notes keep quoting the old handle on purpose — they are a record of what was
 * true when they were written.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('disputes', 'reference_token')) {
            Schema::table('disputes', function (Blueprint $table) {
                $table->string('reference_token', 16)->nullable()->after('reference');
            });
        }

        // reference carries a unique index, and the id stays at the front of the
        // new handle, so a backfilled value can never collide with another row's.
        foreach (DB::table('disputes')->orderBy('id')->pluck('id') as $id) {
            $token = Dispute::generateReferenceToken();

            DB::table('disputes')->where('id', $id)->update([
                'reference_token' => $token,
                'reference'       => 'D-' . (int) $id . $token,
            ]);
        }
    }

    public function down(): void
    {
        // Restore the sequential handles so the column is left as this migration
        // found it, then drop the token.
        foreach (DB::table('disputes')->orderBy('id')->pluck('id') as $id) {
            DB::table('disputes')->where('id', $id)->update([
                'reference' => 'D-' . (10000 + (int) $id),
            ]);
        }

        if (Schema::hasColumn('disputes', 'reference_token')) {
            Schema::table('disputes', function (Blueprint $table) {
                $table->dropColumn('reference_token');
            });
        }
    }
};
