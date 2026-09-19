<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('booth_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('device_id', 64); $table->string('client_session_id', 100);
            $table->string('request_hash', 64); $table->json('package');
            $table->string('template_id'); $table->unsignedInteger('amount');
            $table->string('payment_status')->default('pending');
            $table->boolean('charge_attempted')->default(false);
            $table->text('qr_url')->nullable();
            $table->string('transaction_id')->nullable();
            $table->timestamp('expires_at'); $table->timestamp('verified_at')->nullable();
            $table->timestamps(); $table->unique(['device_id', 'client_session_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('booth_orders'); }
};
