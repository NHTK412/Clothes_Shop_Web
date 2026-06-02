<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable()->unique();
            $table->string('name')->nullable();
            $table->string('phone')->nullable()->unique();
            $table->string('password')->nullable();
            $table->string('avatar')->nullable();
            $table->enum('role', [
                'ROLE_ADMIN',
                'ROLE_CUSTOMER',
            ])->default('ROLE_CUSTOMER');
            $table->enum('status', [
                'ACTIVE',
                'INACTIVE',
            ])->default('ACTIVE');
            $table->timestamps();
        });

        Schema::create('oauth_providers', function (Blueprint $table) {
            $table->id()->primary();
            $table->enum('provider', [
                'GOOGLE',
            ])->default('GOOGLE');
            $table->string('provider_id')->nullable();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('oauth_providers');
        Schema::dropIfExists('sessions');
    }
};
