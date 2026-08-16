<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete()->after('reviewed_by');
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
            $table->text('rejection_reason')->nullable()->after('rejected_at');
            $table->timestamp('processed_at')->nullable()->after('rejection_reason');
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete()->after('processed_at');
            $table->string('external_reference')->nullable()->after('completed_by');
            $table->string('currency', 3)->default('BDT')->after('external_reference');
            $table->unsignedBigInteger('wallet_transaction_id')->nullable()->after('currency');
        });
    }
    public function down(): void {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropColumn(['approved_by','approved_at','rejected_at','rejection_reason',
                                'processed_at','completed_by','external_reference','currency','wallet_transaction_id']);
        });
    }
};
