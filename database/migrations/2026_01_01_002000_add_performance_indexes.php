<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Users — common lookups
        Schema::table('users', function (Blueprint $table) {
            if (!$this->hasIndex('users','users_phone_index'))
                $table->index('phone');
            if (!$this->hasIndex('users','users_status_index'))
                $table->index('status');
            if (!$this->hasIndex('users','users_verification_status_index'))
                $table->index('verification_status');
        });
        // Assets — marketplace queries
        Schema::table('assets', function (Blueprint $table) {
            if (!$this->hasIndex('assets','assets_status_seller_id_index'))
                $table->index(['status','user_id']);
            if (!$this->hasIndex('assets','assets_category_id_status_index'))
                $table->index(['category_id','status']);
            if (!$this->hasIndex('assets','assets_price_index'))
                $table->index('price');
        });
        // Orders — dashboard + admin queries
        Schema::table('orders', function (Blueprint $table) {
            if (!$this->hasIndex('orders','orders_buyer_user_id_status_index'))
                $table->index(['buyer_user_id','status']);
            if (!$this->hasIndex('orders','orders_seller_user_id_status_index'))
                $table->index(['seller_user_id','status']);
            if (!$this->hasIndex('orders','orders_paid_at_index'))
                $table->index('paid_at');
        });
        // Audit logs — admin queries
        Schema::table('audit_logs', function (Blueprint $table) {
            if (!$this->hasIndex('audit_logs','audit_logs_user_id_module_index'))
                $table->index(['user_id','module']);
        });
        // Notifications — dashboard unread count
        Schema::table('notifications', function (Blueprint $table) {
            if (!$this->hasIndex('notifications','notifications_notifiable_read_at_index'))
                $table->index(['notifiable_id','read_at']);
        });
    }
    public function down(): void {}

    private function hasIndex(string $table, string $index): bool
    {
        return collect(\Illuminate\Support\Facades\Schema::getIndexes($table))
            ->pluck('name')->contains($index);
    }
};
