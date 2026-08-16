<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->timestamp('resolved_at')->nullable()->after('assigned_to');
            $table->timestamp('closed_at')->nullable()->after('resolved_at');
            $table->timestamp('last_reply_at')->nullable()->after('closed_at');
            $table->timestamp('assigned_at')->nullable()->after('last_reply_at');
            $table->string('resolution_note')->nullable()->after('assigned_at');
            $table->index('assigned_to');
            $table->index('priority');
        });
        Schema::table('support_ticket_messages', function (Blueprint $table) {
            $table->string('attachment_disk')->default('private')->after('attachment_path');
            $table->string('attachment_name')->nullable()->after('attachment_disk');
        });
    }
    public function down(): void {}
};
