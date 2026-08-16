<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone');                    // hashed/masked externally; stored for audit
            $table->string('template')->nullable();
            $table->text('message');
            $table->string('provider')->default('bulksmsbd');
            $table->string('provider_reference')->nullable()->index();
            $table->string('status')->default('pending')->index(); // pending|sent|failed
            $table->text('error_message')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('idempotency_key')->nullable()->unique(); // prevents duplicate sends
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id','status']);
            $table->index(['status','created_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('sms_logs'); }
};
