<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('gateway')->default('uddoktapay');
            $table->string('gateway_payment_id')->nullable()->unique(); // UddoktaPay invoice_id
            $table->string('gateway_transaction_id')->nullable()->index(); // confirmed txn ID
            $table->unsignedBigInteger('amount');       // poisha — what was charged
            $table->string('currency', 3)->default('BDT');
            $table->string('status')->default('pending')->index(); // pending|processing|paid|failed|cancelled|refunded
            $table->text('gateway_response')->nullable(); // raw safe response (no secrets)
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['order_id','status']);
        });
    }
    public function down(): void { Schema::dropIfExists('payments'); }
};
