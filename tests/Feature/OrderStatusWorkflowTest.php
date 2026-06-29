<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Http\Services\OrderService;
use App\Http\Services\VnpayService;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_ghn_moves_cod_order_through_shipping_and_marks_payment_paid_on_delivery(): void
    {
        $order = $this->createOrder(OrderStatus::CONFIRMED, 'COD', 'UNPAID');
        $service = app(OrderService::class);

        $shippingResult = $service->updateStatusFromGhn($order->ghn_order_code, 'picked');

        $this->assertSame(OrderStatus::SHIPPING->value, $shippingResult['new_status']);
        $this->assertSame('UNPAID', $order->payment()->first()->status);

        $completedResult = $service->updateStatusFromGhn($order->ghn_order_code, 'delivered');

        $this->assertSame(OrderStatus::COMPLETED->value, $completedResult['new_status']);
        $this->assertSame('PAID', $order->payment()->first()->status);
    }

    public function test_successful_vnpay_return_confirms_and_marks_order_paid(): void
    {
        config(['services.vnpay.hash_secret' => 'test-secret']);

        $order = $this->createOrder(OrderStatus::PENDING_PAYMENT, 'VNPAY', 'UNPAID');
        $order->payment()->update([
            'transaction_id' => 'TXN-1',
            'payment_details' => [],
        ]);

        $params = [
            'vnp_Amount' => 10000000,
            'vnp_ResponseCode' => '00',
            'vnp_TransactionStatus' => '00',
            'vnp_TxnRef' => 'TXN-1',
        ];
        ksort($params);
        $params['vnp_SecureHash'] = hash_hmac(
            'sha512',
            collect($params)
                ->map(fn ($value, $key) => urlencode($key).'='.urlencode($value))
                ->implode('&'),
            'test-secret'
        );

        $result = app(VnpayService::class)->handleReturn($params);

        $this->assertSame(OrderStatus::CONFIRMED->value, $result['order_status']);
        $this->assertSame('PAID', $result['payment_status']);
    }

    public function test_cancelling_paid_vnpay_order_creates_pending_refund_without_prematurely_refunding_payment(): void
    {
        $order = $this->createOrder(OrderStatus::CONFIRMED, 'VNPAY', 'PAID');
        $order->update(['ghn_order_code' => null]);

        $cancelledOrder = app(OrderService::class)->cancelOrder($order->user, $order->id);

        $this->assertSame(OrderStatus::CANCELLED->value, $cancelledOrder->status);
        $this->assertSame('PAID', $cancelledOrder->payment->status);
        $this->assertSame('pending', $cancelledOrder->refundRequest->status);
        $this->assertEquals(100000, $cancelledOrder->refundRequest->amount);
    }

    private function createOrder(
        OrderStatus $status,
        string $paymentMethod,
        string $paymentStatus
    ): Order {
        $user = User::factory()->create();

        $order = Order::create([
            'user_id' => $user->id,
            'total_price' => 100000,
            'discount_price' => 0,
            'final_price' => 100000,
            'ship_price' => 0,
            'discount_ship_price' => 0,
            'status' => $status->value,
            'ward_code' => '100001',
            'ward_name' => 'Ward 1',
            'province_id' => 1,
            'province_name' => 'Province 1',
            'specific_address' => '123 Test Street',
            'full_name' => 'Test User',
            'phone' => '0900000000',
            'ghn_order_code' => 'GHN-'.$user->id,
        ]);

        $order->payment()->create([
            'method' => $paymentMethod,
            'status' => $paymentStatus,
        ]);

        return $order->load(['user', 'payment']);
    }
}
