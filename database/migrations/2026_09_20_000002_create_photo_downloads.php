<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('photo_downloads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('device_hash', 64);
            $table->string('client_order_id', 100);
            $table->string('image_hash', 64);
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->unique(['device_hash', 'client_order_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('photo_downloads'); }
};
