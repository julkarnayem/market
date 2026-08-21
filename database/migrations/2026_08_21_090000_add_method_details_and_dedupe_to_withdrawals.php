<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two additions to `withdrawals`; nothing existing is altered or dropped.
 *
 * `client_request_id` is the dedupe key for a double-submitted form, the same
 * device dispute_messages already uses: unique per user, so the second insert of
 * a double-click collides instead of reserving the balance twice.
 *
 * The bank columns let a payout go somewhere other than a mobile wallet. They
 * are nullable because every existing row is an MFS payout and stays one — the
 * `method` column already distinguished them, it just only ever held 'mfs'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->string('client_request_id', 64)->nullable()->after('user_id');
            $table->string('bank_account_name', 120)->nullable()->after('mfs_number');
            $table->string('bank_account_number', 64)->nullable()->after('bank_account_name');
            $table->string('bank_name', 120)->nullable()->after('bank_account_number');
            $table->string('bank_branch', 120)->nullable()->after('bank_name');
            $table->timestamp('cancelled_at')->nullable()->after('rejected_at');

            $table->unique(['user_id', 'client_request_id']);
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'client_request_id']);
            $table->dropColumn([
                'client_request_id', 'bank_account_name', 'bank_account_number',
                'bank_name', 'bank_branch', 'cancelled_at',
            ]);
        });
    }
};
