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
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('description')->nullable();
            $table->decimal('discount_amount', 8, 2);
            $table->decimal('max_discount_amount', 8, 2)->nullable(); // giá trị tối đa của voucher
            $table->enum('discount_type', ['ORDER', 'SHIPPING']);
            $table->boolean('is_active')->default(true);
            $table->integer('usage_limit');
            $table->date('expiry_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
