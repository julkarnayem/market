<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('asset_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();  // null = guest
            $table->string('viewer_hash', 64)->index();         // sha256(ip+ua+date)
            $table->date('viewed_date')->index();
            $table->timestamps();
            $table->unique(['asset_id','viewer_hash','viewed_date']);
        });

        // Add views_count to assets for fast sorting
        Schema::table('assets', function (Blueprint $table) {
            $table->unsignedInteger('views_count')->default(0)->after('sold_quantity');
            $table->index('views_count');
        });
    }
    public function down(): void {
        Schema::dropIfExists('asset_views');
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn('views_count');
        });
    }
};
