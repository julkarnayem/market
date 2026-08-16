<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('asset_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('disk')->default('public');   // 'public'
            $table->string('path');                      // relative path on disk
            $table->string('original_name')->nullable(); // safe-stored original name
            $table->string('mime_type')->nullable();
            $table->unsignedInteger('size_bytes')->nullable();
            $table->boolean('is_cover')->default(false)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['asset_id','sort_order']);
        });

        // Enhance assets table: add admin_notes, changes_requested_note, policy_accepted_at
        Schema::table('assets', function (Blueprint $table) {
            $table->text('admin_notes')->nullable()->after('rejection_reason');
            $table->text('changes_requested_note')->nullable()->after('admin_notes');
            $table->timestamp('policy_accepted_at')->nullable()->after('changes_requested_note');
        });
    }
    public function down(): void {
        Schema::dropIfExists('asset_images');
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['admin_notes','changes_requested_note','policy_accepted_at']);
        });
    }
};
