<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('module')->nullable()->index()->after('action');
            $table->text('reason')->nullable()->after('new_values');
            // No explicit index() calls here: nullableMorphs('auditable') in
            // 001600_create_audit_logs_table already creates
            // audit_logs_auditable_type_auditable_id_index, and ->index() above
            // already indexes `module`. Re-declaring them aborts the migration
            // with "index already exists" on a fresh database (e.g. the sqlite
            // in-memory database the test suite uses).
        });
    }
    public function down(): void {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn(['module','reason']);
        });
    }
};
