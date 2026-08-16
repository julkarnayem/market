<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('name');
            $table->string('phone')->nullable()->unique()->after('email');
            $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
            $table->string('profile_photo_path')->nullable()->after('phone_verified_at');

            $table->string('status')->default('active')->index()->after('profile_photo_path');
            $table->string('verification_status')->default('not_submitted')->index()->after('status');

            $table->timestamp('suspended_at')->nullable()->after('verification_status');
            $table->string('suspended_reason')->nullable()->after('suspended_at');

            $table->text('bio')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'username', 'phone', 'phone_verified_at', 'profile_photo_path',
                'status', 'verification_status', 'suspended_at', 'suspended_reason',
                'last_login_at', 'deleted_at',
            ]);
        });
    }
};
