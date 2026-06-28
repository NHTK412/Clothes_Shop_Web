<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_customers(): void
    {
        $customer = User::factory()->create([
            'role' => 'ROLE_CUSTOMER',
            'status' => 'ACTIVE',
        ]);

        Order::create([
            'user_id' => $customer->id,
            'total_price' => 90000,
            'discount_price' => 0,
            'final_price' => 90000,
            'ship_price' => 15000,
            'discount_ship_price' => 0,
            'status' => 'COMPLETED',
            'ward_code' => '100001',
            'ward_name' => 'Phường 1',
            'province_id' => 1,
            'province_name' => 'Thành phố A',
            'specific_address' => '123 Đường A',
            'full_name' => 'Nguyễn Văn A',
            'phone' => '0901234567',
        ]);

        Order::create([
            'user_id' => $customer->id,
            'total_price' => 110000,
            'discount_price' => 0,
            'final_price' => 110000,
            'ship_price' => 15000,
            'discount_ship_price' => 0,
            'status' => 'COMPLETED',
            'ward_code' => '100002',
            'ward_name' => 'Phường 2',
            'province_id' => 1,
            'province_name' => 'Thành phố A',
            'specific_address' => '456 Đường B',
            'full_name' => 'Nguyễn Văn A',
            'phone' => '0901234567',
        ]);

        $admin = User::factory()->create([
            'role' => 'ROLE_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($admin, 'api')->getJson('/api/customers');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'success',
                'message',
                'data' => [
                    'data' => [
                        ['id', 'name', 'email', 'phone', 'role', 'status', 'total_orders', 'total_spent'],
                    ],
                ],
            ])
            ->assertJsonFragment([
                'total_orders' => 2,
                'total_spent' => 200000.0,
            ]);
    }

    public function test_admin_can_view_customer_with_order_summary(): void
    {
        $customer = User::factory()->create([
            'role' => 'ROLE_CUSTOMER',
            'status' => 'ACTIVE',
        ]);

        Order::create([
            'user_id' => $customer->id,
            'total_price' => 120000,
            'discount_price' => 0,
            'final_price' => 120000,
            'ship_price' => 20000,
            'discount_ship_price' => 0,
            'status' => 'COMPLETED',
            'ward_code' => '100003',
            'ward_name' => 'Phường 3',
            'province_id' => 2,
            'province_name' => 'Thành phố B',
            'specific_address' => '789 Đường C',
            'full_name' => 'Trần Thị B',
            'phone' => '0912345678',
        ]);

        $admin = User::factory()->create([
            'role' => 'ROLE_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($admin, 'api')->getJson("/api/customers/{$customer->id}");

        $response->assertOk()
            ->assertJson([ 
                'data' => [
                    'id' => $customer->id,
                    'total_orders' => 1,
                    'total_spent' => 120000.0,
                ],
            ]);
    }

    public function test_non_admin_cannot_manage_customers(): void
    {
        $customer = User::factory()->create([
            'role' => 'ROLE_CUSTOMER',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($customer, 'api')->getJson('/api/customers');

        $response->assertForbidden();
    }
}
