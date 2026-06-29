<?php

namespace Tests\Feature;

use Database\Seeders\AdminUserSeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->nullable()->unique();
            $table->string('name')->nullable();
            $table->string('phone')->nullable()->unique();
            $table->string('password')->nullable();
            $table->string('avatar')->nullable();
            $table->enum('role', ['ROLE_ADMIN', 'ROLE_CUSTOMER'])->default('ROLE_CUSTOMER');
            $table->enum('status', ['ACTIVE', 'INACTIVE'])->default('ACTIVE');
            $table->timestamps();
        });

        config()->set('admin', [
            'name' => 'Shop Admin',
            'email' => 'admin@shop.test',
            'password' => 'secure-password',
            'phone' => '0900000000',
        ]);
    }

    public function test_it_creates_the_configured_admin_only_once(): void
    {
        $this->seed(AdminUserSeeder::class);
        $this->seed(AdminUserSeeder::class);

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'name' => 'Shop Admin',
            'email' => 'admin@shop.test',
            'phone' => '0900000000',
            'role' => 'ROLE_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $password = Schema::getConnection()
            ->table('users')
            ->where('email', 'admin@shop.test')
            ->value('password');

        $this->assertTrue(Hash::check('secure-password', $password));
    }
}
