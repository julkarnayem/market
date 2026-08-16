<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('orders', function (Blueprint $table) {
            // Human-friendly order number (ORD-YYYYMMDD-XXXXXX)
            $table->string('order_number', 30)->unique()->nullable()->after('reference');
            // Offer linkage
            $table->foreignId('offer_id')->nullable()->constrained('offers')->nullOnDelete()->after('asset_id');
            // Separate status fields (spec requirement)
            $table->string('payment_status')->default('pending')->index()->after('status');
            $table->string('delivery_status')->default('not_started')->index()->after('payment_status');
            $table->string('dispute_status')->nullable()->after('delivery_status');
            // Timestamps
            $table->timestamp('buyer_received_at')->nullable()->after('delivered_at');
            $table->timestamp('auto_completed_at')->nullable()->after('completed_at');
            $table->timestamp('seller_earning_available_at')->nullable()->after('earning_release_at');
        });
    }
    public function down(): void {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'order_number','offer_id','payment_status','delivery_status',
                'dispute_status','buyer_received_at','auto_completed_at',
                'seller_earning_available_at',
            ]);
        });
    }
};
