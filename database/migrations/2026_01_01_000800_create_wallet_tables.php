<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('available_balance')->default(0); // poisha
            $table->unsignedBigInteger('pending_balance')->default(0);   // poisha (locked)
            $table->string('currency', 3)->default('BDT');
            $table->timestamps();
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type')->index();
            $table->bigInteger('amount');                    // signed poisha
            $table->unsignedBigInteger('available_after');   // poisha
            $table->unsignedBigInteger('pending_after');     // poisha
            $table->nullableMorphs('reference');
            $table->timestamp('available_at')->nullable();   // earning lock release
            $table->json('meta')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
    }
};
