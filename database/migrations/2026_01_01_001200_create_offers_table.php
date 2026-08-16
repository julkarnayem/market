<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('amount'); // poisha
            $table->unsignedInteger('quantity')->default(1);
            $table->string('status')->default('pending')->index(); // pending|accepted|rejected|expired|cancelled
            $table->timestamp('expires_at');                       // created + 8h
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['asset_id', 'status']);
            $table->index(['buyer_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
