<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('is_prohibited')->default(false)->after('is_active');
            $table->boolean('is_restricted')->default(false)->after('is_prohibited');
        });
        // Extend category_attributes with more types and validation
        Schema::table('category_attributes', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('position');
            $table->string('validation_rules')->nullable()->after('is_active'); // e.g. "min:0|max:999999999"
            $table->string('placeholder')->nullable()->after('validation_rules');
            $table->string('unit')->nullable()->after('placeholder'); // e.g. "subscribers", "$/month"
        });
    }
    public function down(): void {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['is_prohibited','is_restricted']);
        });
        Schema::table('category_attributes', function (Blueprint $table) {
            $table->dropColumn(['is_active','validation_rules','placeholder','unit']);
        });
    }
};
