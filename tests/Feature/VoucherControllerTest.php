<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VoucherControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('description')->nullable();
            $table->decimal('discount_amount', 8, 2);
            $table->decimal('max_discount_amount', 8, 2)->nullable();
            $table->enum('discount_type', ['ORDER', 'SHIPPING']);
            $table->boolean('is_active')->default(true);
            $table->integer('usage_limit');
            $table->date('expiry_date');
            $table->timestamps();
        });
    }

    public function test_admin_can_complete_voucher_crud_with_an_automatically_generated_code(): void
    {
        $admin = $this->userWithRole('ROLE_ADMIN');

        $createResponse = $this->actingAs($admin, 'api')->postJson('/api/voucher', [
            'description' => 'Giảm 10% đơn hàng',
            'discount_amount' => 10,
            'max_discount_amount' => 50000,
            'discount_type' => 'ORDER',
            'usage_limit' => 100,
            'expiry_date' => now()->addMonth()->toDateString(),
        ])->assertCreated();

        $code = $createResponse->json('data.code');

        $this->assertMatchesRegularExpression('/^[A-Z0-9]{10}$/', $code);

        $this->actingAs($admin, 'api')->getJson('/api/voucher?status=valid')
            ->assertOk()
            ->assertJsonPath('data.items.0.code', $code)
            ->assertJsonPath('data.pagination.totalItems', 1);

        $this->actingAs($admin, 'api')->putJson("/api/voucher/{$code}", [
            'code' => 'summer2026',
            'description' => 'Giảm 15% đơn hàng',
            'discount_amount' => 15,
            'usage_limit' => 50,
        ])->assertOk()
            ->assertJsonPath('data.code', 'SUMMER2026')
            ->assertJsonPath('data.discount_amount', '15.00')
            ->assertJsonPath('data.usage_limit', 50);

        $this->actingAs($admin, 'api')->deleteJson('/api/voucher/SUMMER2026')
            ->assertOk()
            ->assertJsonPath('data', true);

        $this->assertDatabaseHas('vouchers', [
            'code' => 'SUMMER2026',
            'is_active' => false,
        ]);
    }

    public function test_deactivated_voucher_code_cannot_be_reused(): void
    {
        $admin = $this->userWithRole('ROLE_ADMIN');

        $voucher = Voucher::create([
            'code' => 'WELCOME10',
            'description' => 'Voucher cũ',
            'discount_amount' => 10,
            'discount_type' => 'ORDER',
            'is_active' => true,
            'usage_limit' => 10,
            'expiry_date' => now()->addMonth()->toDateString(),
        ]);
        $voucher->update(['is_active' => false]);

        $this->actingAs($admin, 'api')->postJson('/api/voucher', [
            'code' => 'WELCOME10',
            'discount_amount' => 10,
            'discount_type' => 'ORDER',
            'usage_limit' => 10,
            'expiry_date' => now()->addMonth()->toDateString(),
        ])->assertUnprocessable();
    }

    public function test_customer_cannot_manage_vouchers(): void
    {
        $customer = $this->userWithRole('ROLE_CUSTOMER');

        $this->actingAs($customer, 'api')->getJson('/api/voucher')
            ->assertForbidden();
    }

    public function test_admin_can_view_an_inactive_voucher_but_customer_cannot_use_it(): void
    {
        $voucher = Voucher::create([
            'code' => 'INACTIVE10',
            'discount_amount' => 10,
            'discount_type' => 'ORDER',
            'is_active' => false,
            'usage_limit' => 10,
            'expiry_date' => now()->addMonth()->toDateString(),
        ]);

        $this->actingAs($this->userWithRole('ROLE_ADMIN'), 'api')
            ->getJson("/api/voucher/{$voucher->id}")
            ->assertOk()
            ->assertJsonPath('data.code', 'INACTIVE10');

        $this->actingAs($this->userWithRole('ROLE_CUSTOMER'), 'api')
            ->getJson('/api/voucher/INACTIVE10')
            ->assertUnprocessable();
    }

    private function userWithRole(string $role): User
    {
        $user = new User;
        $user->forceFill([
            'id' => $role === 'ROLE_ADMIN' ? 1 : 2,
            'role' => $role,
        ]);

        return $user;
    }
}
