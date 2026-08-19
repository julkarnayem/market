<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Public bids on single/unique listings.
 *
 * Bids are a public, listing-level negotiation and are deliberately *not*
 * chat messages — they live here, not in `messages`, and never appear in a
 * conversation. Amounts are integer poisha like every other money column.
 *
 * The only rule on a new bid is that it beats the current top bid, which is
 * why (asset_id, status, amount) is indexed: BidService reads the top bid
 * under a row lock on the asset before inserting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bidder_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_user_id')->constrained('users')->cascadeOnDelete();

            $table->unsignedBigInteger('amount'); // poisha

            // active | outbid | accepted | rejected | cancelled | expired
            $table->string('status', 20)->default('active');

            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('outbid_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index(['asset_id', 'status', 'amount']);
            $table->index(['bidder_user_id', 'status']);
            $table->index(['seller_user_id', 'status']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('bid_id')->nullable()->after('offer_id')
                ->constrained('bids')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['bid_id']);
            $table->dropColumn('bid_id');
        });

        Schema::dropIfExists('bids');
    }
};
