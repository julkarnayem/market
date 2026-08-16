<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('seller_verifications', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('status');
            $table->text('admin_notes')->nullable()->after('rejection_reason');
            $table->unsignedSmallInteger('attempt_number')->default(1)->after('admin_notes');
        });
    }
    public function down(): void {
        Schema::table('seller_verifications', function (Blueprint $table) {
            $table->dropColumn(['submitted_at','admin_notes','attempt_number']);
        });
    }
};
