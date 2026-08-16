<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('fraud_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('signal');           // duplicate_nid|failed_payment|rapid_account|self_purchase_attempt etc.
            $table->unsignedTinyInteger('score_impact')->default(10);
            $table->text('context')->nullable(); // safe json-encoded context (no secrets)
            $table->string('ip_address',45)->nullable();
            $table->timestamps();
            $table->index(['user_id','signal']);
            $table->index(['signal','created_at']);
        });
        // Fraud review queue
        Schema::create('fraud_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending')->index(); // pending|reviewing|cleared|restricted|escalated
            $table->unsignedSmallInteger('risk_score')->default(0);
            $table->json('risk_flags')->nullable();
            $table->text('admin_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index('risk_score');
        });
    }
    public function down(): void {
        Schema::dropIfExists('fraud_reviews');
        Schema::dropIfExists('fraud_events');
    }
};
