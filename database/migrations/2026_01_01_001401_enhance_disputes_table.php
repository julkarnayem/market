<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('disputes', function (Blueprint $table) {
            // Add order linkage if not already there
            if (!Schema::hasColumn('disputes', 'order_id')) {
                $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete()->after('id');
            }
        });
    }
    public function down(): void {}
};
