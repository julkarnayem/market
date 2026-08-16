<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('module')->nullable()->index()->after('action');
            $table->text('reason')->nullable()->after('new_values');
            $table->index(['auditable_type','auditable_id']);
            $table->index('module');
        });
    }
    public function down(): void {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['module','reason']);
        });
    }
};
