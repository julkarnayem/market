<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('is_protected')->default(false)->after('is_admin_role');
            $table->text('description')->nullable()->after('is_protected');
        });
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users','admin_notes')) {
                $table->text('admin_notes')->nullable()->after('bio');
            }
            if (!Schema::hasColumn('users','risk_score')) {
                $table->unsignedSmallInteger('risk_score')->default(0)->after('admin_notes');
                $table->json('risk_flags')->nullable()->after('risk_score');
            }
        });
        // Staff login log
        Schema::create('staff_login_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('ip_address',45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('status')->default('success'); // success|failed
            $table->timestamps();
            $table->index(['user_id','created_at']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('staff_login_logs');
    }
};
