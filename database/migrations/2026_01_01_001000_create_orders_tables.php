<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('buyer_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('seller_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();

            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price');   // poisha
            $table->unsignedBigInteger('subtotal');     // poisha

            // ---- FEE SNAPSHOT (captured at creation; never recalculated) ----
            $table->unsignedInteger('seller_fee_bp');           // basis points
            $table->unsignedBigInteger('seller_fee_amount');    // poisha
            $table->boolean('buyer_fee_enabled')->default(false);
            $table->string('buyer_fee_type')->nullable();
            $table->unsignedInteger('buyer_fee_bp')->nullable(); // basis points (if percentage)
            $table->unsignedBigInteger('buyer_fee_amount')->default(0);  // poisha
            $table->unsignedBigInteger('platform_commission');  // poisha
            $table->unsignedBigInteger('buyer_total');          // poisha
            $table->unsignedBigInteger('seller_earning');       // poisha
            // -----------------------------------------------------------------

            $table->string('currency', 3)->default('BDT');
            $table->string('status')->default('pending_payment')->index();

            $table->string('payment_gateway')->nullable();
            $table->string('payment_reference')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('auto_complete_at')->nullable();
            $table->timestamp('earning_release_at')->nullable();

            $table->timestamps();

            $table->index(['buyer_user_id', 'status']);
            $table->index(['seller_user_id', 'status']);
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('orders');
    }
};
