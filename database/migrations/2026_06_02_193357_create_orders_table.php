<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->decimal('total_price', 10, 2);
            $table->decimal('discount_price', 10, 2)->nullable();
            $table->decimal('final_price', 10, 2);
            $table->decimal('ship_price', 10, 2)->nullable();
            $table->decimal('discount_ship_price', 10, 2);
            $table->enum('status', ['PENDING_PAYMENT', 'CONFIRMED', 'SHIPPING', 'COMPLETED', 'CANCELLED', 'RETURNED'])->default('PENDING_PAYMENT');
            $table->foreignId('user_id')->constrained();
            $table->string('ward_code');
            $table->string('ward_name');
            $table->unsignedInteger('province_id');
            $table->string('province_name');
            $table->string('specific_address');
            $table->string('full_name');
            $table->string('phone', 20);
            $table->string('ghn_order_code')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
