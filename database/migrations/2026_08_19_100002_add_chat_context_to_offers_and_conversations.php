<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Moves offers into the chat, and gives conversations a listing context.
 *
 * The listing-level "Make an Offer" is gone; what remains of this table is the
 * *custom offer*, which is created inside a buyer↔seller conversation, is
 * private to that conversation, and can be sent in either direction. So:
 *
 *   conversations.asset_id     — the listing a "Contact Seller" chat is about,
 *                               which is also the key used to find an existing
 *                               conversation instead of opening a duplicate.
 *   offers.conversation_id     — the chat the offer belongs to.
 *   offers.message_id          — the message whose card renders the offer.
 *   offers.created_by_user_id  — direction. Whoever did *not* create it responds,
 *                               but the buyer always pays.
 *   offers.delivery_days       — the delivery term quoted with the offer.
 *   offers.paid_at/completed_at — the two new terminal states.
 *
 * `offer_message` replaces the old `buyer_message` name only in intent; the
 * column is left alone so no data moves and no other reader breaks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('asset_id')->nullable()->after('order_id')
                ->constrained('assets')->nullOnDelete();
            $table->index(['asset_id', 'type']);
        });

        Schema::table('offers', function (Blueprint $table) {
            $table->foreignId('conversation_id')->nullable()->after('asset_id')
                ->constrained('conversations')->nullOnDelete();
            $table->foreignId('message_id')->nullable()->after('conversation_id')
                ->constrained('messages')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->after('seller_user_id')
                ->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('delivery_days')->nullable()->after('quantity');
            $table->timestamp('paid_at')->nullable()->after('expired_at');
            $table->timestamp('completed_at')->nullable()->after('paid_at');
            $table->index(['conversation_id', 'status']);
        });

        // Every pre-existing offer was buyer-created — that was the only direction.
        DB::table('offers')->whereNull('created_by_user_id')
            ->update(['created_by_user_id' => DB::raw('buyer_user_id')]);
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropIndex(['conversation_id', 'status']);
            $table->dropForeign(['conversation_id']);
            $table->dropForeign(['message_id']);
            $table->dropForeign(['created_by_user_id']);
            $table->dropColumn([
                'conversation_id', 'message_id', 'created_by_user_id',
                'delivery_days', 'paid_at', 'completed_at',
            ]);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex(['asset_id', 'type']);
            $table->dropForeign(['asset_id']);
            $table->dropColumn('asset_id');
        });
    }
};
