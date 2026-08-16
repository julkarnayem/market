<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('disputes', function (Blueprint $table) {
            $table->text('description')->nullable()->after('reason');
            $table->text('admin_notes')->nullable()->after('resolution_note');
            $table->string('resolution_type')->nullable()->after('resolution');
            // Earning released flag for idempotency
        });
        // Dispute evidence table
        Schema::create('dispute_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();
            $table->string('role'); // buyer|seller
            $table->text('message')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_disk')->default('private');
            $table->string('file_original_name')->nullable();
            $table->timestamps();
            $table->index('dispute_id');
        });
        // Add earning_released flag to orders (idempotency for seller earning release)
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('earning_released')->default(false)->after('seller_earning_available_at');
        });
    }
    public function down(): void {
        Schema::dropIfExists('dispute_evidence');
        Schema::table('disputes', function (Blueprint $table) {
            $table->dropColumn(['description','admin_notes','resolution_type']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('earning_released');
        });
    }
};
