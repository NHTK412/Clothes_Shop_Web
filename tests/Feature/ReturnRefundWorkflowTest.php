<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\RefundRequest;
use App\Models\ReturnRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReturnRefundWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_list_and_cancel_a_return_request(): void
    {
        $order = $this->createOrder();

        $this->actingAs($order->user, 'api')
            ->getJson('/api/order')
            ->assertOk()
            ->assertJsonPath('data.items.0.can_request_return', true)
            ->assertJsonPath('data.items.0.return_request', null);

        $created = $this->actingAs($order->user, 'api')->postJson('/api/return-requests', [
            'order_id' => $order->id,
            'reason' => 'Sản phẩm không đúng mô tả',
        ]);

        $created->assertCreated()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.order_id', $order->id)
            ->assertJsonPath('data.order.status', OrderStatus::RETURNED->value);

        $this->assertSame(OrderStatus::RETURNED->value, $order->fresh()->status);

        $returnId = $created->json('data.id');

        $this->actingAs($order->user, 'api')
            ->getJson('/api/order')
            ->assertOk()
            ->assertJsonPath('data.items.0.status', OrderStatus::RETURNED->value)
            ->assertJsonPath('data.items.0.can_request_return', false)
            ->assertJsonPath('data.items.0.return_request.id', $returnId)
            ->assertJsonPath('data.items.0.return_request.status', 'pending');

        $this->actingAs($order->user, 'api')
            ->getJson('/api/return-requests')
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.id', $returnId);

        $this->actingAs($order->user, 'api')
            ->patchJson("/api/return-requests/{$returnId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.order.status', OrderStatus::COMPLETED->value);

        $this->assertSame(OrderStatus::COMPLETED->value, $order->fresh()->status);
    }

    public function test_admin_approval_creates_ghn_return_shipment_and_refund_then_completes_refund(): void
    {
        config([
            'services.ghn.base_url' => 'https://dev-online-gateway.ghn.vn',
            'services.ghn.token' => 'ghn-token',
            'services.ghn.shop_id' => '12345',
            'services.ghn.return_shop' => [
                'name' => 'Clothes Shop',
                'phone' => '0366408263',
                'address' => 'Đường NA12',
                'ward_code' => '1003601',
                'province_name' => 'Hồ Chí Minh',
            ],
        ]);

        Http::fake([
            'https://dev-online-gateway.ghn.vn/shiip/public-api/v2/shipping-order/create' => Http::response([
                'code' => 200,
                'data' => [
                    'order_code' => 'LALHPX',
                    'total_fee' => 49500,
                    'expected_delivery_time' => '2026-07-02T16:59:59Z',
                ],
                'message' => 'Success',
            ]),
        ]);

        $order = $this->createOrder();
        $returnRequest = ReturnRequest::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'reason' => 'Không vừa kích thước',
            'status' => 'pending',
        ]);
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'ROLE_ADMIN'])->save();

        $approved = $this->actingAs($admin, 'api')->patchJson(
            "/api/admin/return-requests/{$returnRequest->id}/status",
            [
                'status' => 'approved',
                'note' => 'Đã thống nhất phương án hoàn tiền với khách',
            ]
        );

        $approved->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.ghn_order_code', 'LALHPX')
            ->assertJsonPath('data.refund_status', 'pending')
            ->assertJsonPath('data.customer.name', $order->full_name)
            ->assertJsonPath('data.customer.email', $order->user->email)
            ->assertJsonPath('data.customer.phone', $order->phone);

        $this->actingAs($admin, 'api')->patchJson(
            "/api/admin/return-requests/{$returnRequest->id}/status",
            [
                'status' => 'approved',
                'note' => 'Cập nhật ghi chú nhưng không tạo lại vận đơn',
            ]
        )->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(function ($request) use ($order) {
            return $request->hasHeader('Token', 'ghn-token')
                && $request->hasHeader('ShopId', '12345')
                && $request['payment_type_id'] === 2
                && $request['from_phone'] === $order->phone
                && $request['to_phone'] === '0366408263'
                && $request['cod_amount'] === 0;
        });

        $refund = RefundRequest::where('order_id', $order->id)->firstOrFail();

        $this->actingAs($admin, 'api')->patchJson("/api/admin/refunds/{$refund->id}", [
            'note' => 'Đã chuyển khoản theo trao đổi',
            'transfer_image' => 'https://cdn.example.com/refunds/proof.jpg',
        ])->assertOk()
            ->assertJsonMissingPath('data.amount')
            ->assertJsonPath('data.transfer_image', 'https://cdn.example.com/refunds/proof.jpg')
            ->assertJsonPath('data.customer.name', $order->full_name)
            ->assertJsonPath('data.customer.email', $order->user->email)
            ->assertJsonPath('data.customer.phone', $order->phone);

        $this->actingAs($admin, 'api')
            ->patchJson("/api/admin/refunds/{$refund->id}/status", ['status' => 'approved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.order.payment_status', 'REFUNDED');
    }

    public function test_ghn_return_status_creates_only_one_refund_when_webhook_is_repeated(): void
    {
        config([
            'services.ghn.base_url' => 'https://dev-online-gateway.ghn.vn',
            'services.ghn.token' => 'ghn-token',
            'services.ghn.webhook_token' => 'webhook-secret',
        ]);

        $order = $this->createOrder(OrderStatus::SHIPPING);

        Http::fake([
            'https://dev-online-gateway.ghn.vn/shiip/public-api/v2/shipping-order/detail*' => Http::response([
                'code' => 200,
                'data' => ['status' => 'Return'],
                'message' => 'Success',
            ]),
        ]);

        $headers = ['X-GHN-Webhook-Token' => 'webhook-secret'];
        $payload = ['order_code' => $order->ghn_order_code];

        $this->withHeaders($headers)->postJson('/api/ghn/webhook/order-status', $payload)
            ->assertOk()
            ->assertJsonPath('data.new_status', OrderStatus::RETURNED->value);
        $this->withHeaders($headers)->postJson('/api/ghn/webhook/order-status', $payload)
            ->assertOk()
            ->assertJsonPath('data.new_status', OrderStatus::RETURNED->value);

        $this->assertSame(1, RefundRequest::where('order_id', $order->id)->count());
    }

    private function createOrder(OrderStatus $status = OrderStatus::COMPLETED): Order
    {
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id,
            'total_price' => 100000,
            'discount_price' => 0,
            'final_price' => 100000,
            'ship_price' => 0,
            'discount_ship_price' => 0,
            'status' => $status->value,
            'ward_code' => '1003544',
            'ward_name' => 'Phường Test',
            'province_id' => 202,
            'province_name' => 'Hồ Chí Minh',
            'specific_address' => '02 Võ Oanh',
            'full_name' => 'Nguyễn Văn Test GHN',
            'phone' => '0777066412',
            'ghn_order_code' => 'GHN-'.$user->id,
        ]);
        $order->payment()->create([
            'method' => 'VNPAY',
            'status' => 'PAID',
        ]);

        return $order->load(['user', 'payment']);
    }

    public function test_admin_rejection_restores_order_to_completed(): void
    {
        $order = $this->createOrder();

        $created = $this->actingAs($order->user, 'api')->postJson('/api/return-requests', [
            'order_id' => $order->id,
            'reason' => 'Muốn đổi sản phẩm',
        ])->assertCreated();

        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'ROLE_ADMIN'])->save();

        $this->actingAs($admin, 'api')->patchJson(
            '/api/admin/return-requests/'.$created->json('data.id').'/status',
            [
                'status' => 'rejected',
                'note' => 'Không đủ điều kiện trả hàng',
            ]
        )->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.order.status', OrderStatus::COMPLETED->value);

        $this->assertSame(OrderStatus::COMPLETED->value, $order->fresh()->status);
    }
}
