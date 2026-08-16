<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('phone_otps', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->index();
            $table->string('otp', 10);
            $table->integer('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('phone_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->unique();
            $table->string('reason')->default('too_many_otp_failures');
            $table->timestamp('blocked_until');
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('phone_blocks');
        Schema::dropIfExists('phone_otps');
    }
};
