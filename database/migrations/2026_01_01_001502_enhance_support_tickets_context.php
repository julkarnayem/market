<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete()->after('reference');
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete()->after('order_id');
            $table->foreignId('withdrawal_id')->nullable()->constrained()->nullOnDelete()->after('asset_id');
            $table->boolean('has_unread_staff_reply')->default(false)->after('resolution_note');
            $table->index('order_id');
            $table->index('asset_id');
        });
        // Also add internal_only flag to ticket messages
        Schema::table('support_ticket_messages', function (Blueprint $table) {
            $table->boolean('is_internal_note')->default(false)->after('is_staff_reply');
            $table->index('support_ticket_id');
        });
    }
    public function down(): void {}
};
