<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('offers', function (Blueprint $table) {
            $table->timestamp('rejected_at')->nullable()->after('responded_at');
            $table->timestamp('expired_at')->nullable()->after('rejected_at');
            $table->text('buyer_message')->nullable()->after('quantity');
            $table->index('expires_at');
            $table->index(['seller_user_id', 'status']);
        });
    }
    public function down(): void {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn(['rejected_at','expired_at','buyer_message']);
        });
    }
};
