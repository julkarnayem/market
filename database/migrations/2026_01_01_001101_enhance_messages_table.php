<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('message_type')->default('text')->after('sender_user_id'); // text|attachment|system|order_event
            $table->json('metadata')->nullable()->after('body');             // safe structured data
            $table->foreignId('reply_to_id')->nullable()->constrained('messages')->nullOnDelete()->after('metadata');
            $table->string('client_message_id')->nullable()->index()->after('reply_to_id'); // idempotency
            $table->string('attachment_disk')->default('private')->after('attachment_path');
            $table->string('attachment_name')->nullable()->after('attachment_disk');
            $table->string('attachment_mime')->nullable()->after('attachment_name');
            $table->boolean('is_system')->default(false)->after('attachment_mime');
            $table->softDeletes()->after('is_system');
            $table->index('sender_user_id');
            // Unique idempotency per sender+conversation
            $table->unique(['conversation_id','sender_user_id','client_message_id'], 'messages_idempotency_unique');
        });
        // Enhance conversations
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('status')->default('active')->after('last_message_at'); // active|closed|archived
            $table->foreignId('last_message_id')->nullable()->after('status');
        });
    }
    public function down(): void {}
};
