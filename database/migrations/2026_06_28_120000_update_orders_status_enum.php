<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'status')) {
            DB::statement("ALTER TABLE `orders` MODIFY COLUMN `status` ENUM('pending','processing','completed','cancelled','returned') NOT NULL DEFAULT 'pending'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'status')) {
            DB::statement("ALTER TABLE `orders` MODIFY COLUMN `status` ENUM('pending','processing','completed','cancelled') NOT NULL DEFAULT 'pending'");
        }
    }
};
