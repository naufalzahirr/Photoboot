<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('manual_codes', function (Blueprint $table) {
            $table->id(); $table->uuid('batch_id')->index();
            $table->string('code', 12)->unique(); $table->string('booth_name', 100); $table->json('package');
            $table->string('package_id')->index();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('distributed_by')->nullable()->constrained('users');
            $table->timestamp('distributed_at')->nullable();
            $table->timestamp('redeemed_at')->nullable();
            $table->foreignId('redeemed_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('manual_codes'); }
};
