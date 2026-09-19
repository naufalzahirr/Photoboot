<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('booth_orders', fn (Blueprint $table) => $table->timestamp('fulfillment_reserved_at')->nullable());
    }
    public function down(): void { Schema::table('booth_orders', fn (Blueprint $table) => $table->dropColumn('fulfillment_reserved_at')); }
};
