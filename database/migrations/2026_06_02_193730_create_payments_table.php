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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->enum('method', [
                'COD',
                'VNPAY',
            ]);
            $table->enum('status', ['UNPAID', 'PAID', 'REFUNDED',
            ])->default('UNPAID');
            $table->string('transaction_id')->nullable();
            $table->json('payment_details')->nullable();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
