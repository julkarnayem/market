<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('promotions', function (Blueprint $table) {
            $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete()->after('user_id');
            $table->string('payment_status')->default('paid')->after('status'); // paid|pending|refunded|failed
            $table->string('payment_reference')->nullable()->after('payment_status');
            $table->unsignedBigInteger('wallet_transaction_id')->nullable()->after('payment_reference');
            $table->string('currency', 3)->default('BDT')->after('wallet_transaction_id');
            // Admin manual feature
            $table->foreignId('featured_by')->nullable()->constrained('users')->nullOnDelete()->after('created_by');
            $table->timestamp('admin_featured_at')->nullable()->after('featured_by');
            $table->timestamp('admin_unfeatured_at')->nullable()->after('admin_featured_at');
            $table->text('admin_note')->nullable()->after('admin_unfeatured_at');
            // Expiry warning tracking (idempotency)
            $table->timestamp('warning_sent_at')->nullable()->after('admin_note');
            // Indexes
            $table->index(['asset_id','status','starts_at','ends_at']);
            $table->index(['seller_id','status']);
        });
    }
    public function down(): void {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn(['seller_id','payment_status','payment_reference','wallet_transaction_id',
                                'currency','featured_by','admin_featured_at','admin_unfeatured_at',
                                'admin_note','warning_sent_at']);
        });
    }
};
