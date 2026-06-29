<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_all_orders(): void
    {
        $customer = User::factory()->create([
            'role' => 'ROLE_CUSTOMER',
            'status' => 'ACTIVE',
        ]);

        Order::create([
            'user_id' => $customer->id,
            'total_price' => 100000,
            'discount_price' => 0,
            'final_price' => 100000,
            'ship_price' => 20000,
            'discount_ship_price' => 0,
            'status' => 'completed',
            'ward_code' => '100001',
            'ward_name' => 'Phường 1',
            'province_id' => 1,
            'province_name' => 'Thành phố A',
            'specific_address' => '123 Đường A',
            'full_name' => 'Nguyễn Văn A',
            'phone' => '0901234567',
        ]);

        $admin = User::factory()->create([
            'role' => 'ROLE_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($admin, 'api')->getJson('/api/admin/orders');

        $response->assertOk()
            ->assertJsonStructure([
                'status',
                'success',
                'message',
                'data' => [
                    'data' => [
                        ['id', 'status', 'final_price', 'customer'],
                    ],
                ],
            ]);
    }

    public function test_admin_can_view_order_detail(): void
    {
        $customer = User::factory()->create([
            'role' => 'ROLE_CUSTOMER',
            'status' => 'ACTIVE',
        ]);

        $order = Order::create([
            'user_id' => $customer->id,
            'total_price' => 120000,
            'discount_price' => 0,
            'final_price' => 120000,
            'ship_price' => 20000,
            'discount_ship_price' => 0,
            'status' => 'processing',
            'ward_code' => '100002',
            'ward_name' => 'Phường 2',
            'province_id' => 2,
            'province_name' => 'Thành phố B',
            'specific_address' => '456 Đường B',
            'full_name' => 'Trần Thị B',
            'phone' => '0912345678',
        ]);

        $admin = User::factory()->create([
            'role' => 'ROLE_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($admin, 'api')->getJson("/api/admin/orders/{$order->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.customer.id', $customer->id);
    }

    public function test_admin_can_get_order_summary(): void
    {
        $customer = User::factory()->create([
            'role' => 'ROLE_CUSTOMER',
            'status' => 'ACTIVE',
        ]);

        Order::create([
            'user_id' => $customer->id,
            'total_price' => 100000,
            'discount_price' => 0,
            'final_price' => 100000,
            'ship_price' => 15000,
            'discount_ship_price' => 0,
            'status' => 'completed',
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
            'total_price' => 200000,
            'discount_price' => 0,
            'final_price' => 200000,
            'ship_price' => 20000,
            'discount_ship_price' => 0,
            'status' => 'completed',
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

        $response = $this->actingAs($admin, 'api')->getJson('/api/admin/orders/summary');

        $response->assertOk()
            ->assertJsonPath('data.total_revenue', 300000)
            ->assertJsonPath('data.total_orders', 2)
            ->assertJsonPath('data.average_order_value', 150000);
    }

    public function test_list_orders_for_specific_customer(): void
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
            'status' => 'completed',
            'ward_code' => '100010',
            'ward_name' => 'Phường X',
            'province_id' => 1,
            'province_name' => 'Thành phố X',
            'specific_address' => '1 Đường X',
            'full_name' => 'Khách Hàng X',
            'phone' => '0900000000',
        ]);

        Order::create([
            'user_id' => $customer->id,
            'total_price' => 110000,
            'discount_price' => 0,
            'final_price' => 110000,
            'ship_price' => 15000,
            'discount_ship_price' => 0,
            'status' => 'completed',
            'ward_code' => '100011',
            'ward_name' => 'Phường Y',
            'province_id' => 1,
            'province_name' => 'Thành phố X',
            'specific_address' => '2 Đường Y',
            'full_name' => 'Khách Hàng X',
            'phone' => '0900000000',
        ]);

        $admin = User::factory()->create([
            'role' => 'ROLE_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($admin, 'api')->getJson("/api/admin/customers/{$customer->id}/orders");

        $response->assertOk()
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonCount(2, 'data.data');
    }

    public function test_admin_can_list_inventory_stock(): void
    {
        $product = Product::create([
            'name' => 'Áo thun test',
            'description' => 'Mô tả sản phẩm',
            'price' => 150000,
            'discount_price' => null,
        ]);

        ProductVariant::create([
            'sku' => 'SKU-INV-001',
            'price' => 150000,
            'discount_price' => null,
            'stock' => 12,
            'product_id' => $product->id,
        ]);

        $admin = User::factory()->create([
            'role' => 'ROLE_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($admin, 'api')->getJson('/api/admin/inventory');

        $response->assertOk()
            ->assertJsonPath('data.items.0.stock', 12)
            ->assertJsonPath('data.items.0.product_name', 'Áo thun test');
    }

    public function test_admin_can_update_variant_stock(): void
    {
        $product = Product::create([
            'name' => 'Quần jean test',
            'description' => 'Mô tả sản phẩm',
            'price' => 250000,
            'discount_price' => null,
        ]);

        $variant = ProductVariant::create([
            'sku' => 'SKU-INV-002',
            'price' => 250000,
            'discount_price' => null,
            'stock' => 3,
            'product_id' => $product->id,
        ]);

        $admin = User::factory()->create([
            'role' => 'ROLE_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($admin, 'api')->patchJson("/api/admin/inventory/{$variant->id}", [
            'stock' => 25,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.stock', 25);

        $this->assertDatabaseHas('product_variants', [
            'id' => $variant->id,
            'stock' => 25,
        ]);
    }

    public function test_admin_can_stock_in_and_stock_out(): void
    {
        $product = Product::create([
            'name' => 'Giày test',
            'description' => 'Mô tả sản phẩm',
            'price' => 300000,
            'discount_price' => null,
        ]);

        $variant = ProductVariant::create([
            'sku' => 'SKU-INV-003',
            'price' => 300000,
            'discount_price' => null,
            'stock' => 5,
            'product_id' => $product->id,
        ]);

        $admin = User::factory()->create([
            'role' => 'ROLE_ADMIN',
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($admin, 'api')->postJson('/api/admin/inventory/stock-in', [
            'variant_id' => $variant->id,
            'quantity' => 7,
        ])->assertOk()
            ->assertJsonPath('data.stock', 12);

        $this->actingAs($admin, 'api')->postJson('/api/admin/inventory/stock-out', [
            'variant_id' => $variant->id,
            'quantity' => 4,
        ])->assertOk()
            ->assertJsonPath('data.stock', 8);
    }

    public function test_non_admin_cannot_access_admin_orders(): void
    {
        $customer = User::factory()->create([
            'role' => 'ROLE_CUSTOMER',
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($customer, 'api')->getJson('/api/admin/orders');

        $response->assertForbidden();
    }
}
