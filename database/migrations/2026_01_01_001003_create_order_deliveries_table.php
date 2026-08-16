<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('order_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivered_by')->constrained('users')->cascadeOnDelete();
            $table->text('delivery_note')->nullable();   // seller message to buyer
            $table->text('delivery_data')->nullable();   // encrypted delivery credentials
            $table->string('attachment_path')->nullable(); // private disk
            $table->string('attachment_disk')->default('private');
            $table->timestamps();
            $table->index('order_id');
        });
    }
    public function down(): void { Schema::dropIfExists('order_deliveries'); }
};
