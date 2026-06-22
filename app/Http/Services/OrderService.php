<?php

namespace App\Http\Services;

use App\Models\Address;
use App\Models\Cart;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function createOrder(
        User $user,
        int $addressId,
        string $paymentMethod = 'COD'
    ): Order {
        return DB::transaction(function () use ($user, $addressId, $paymentMethod) {
            $address = Address::where('user_id', $user->id)->findOrFail($addressId);

            $cart = Cart::with('items.productVariant')
                ->where('user_id', $user->id)
                ->first();

            if (! $cart || $cart->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => 'Cart is empty.',
                ]);
            }

            $totalPrice = 0;
            $totalDiscount = 0;
            $finalPrice = 0;
            $orderDetails = [];

            foreach ($cart->items as $item) {
                $variant = $item->productVariant()->lockForUpdate()->first();

                if (! $variant) {
                    throw ValidationException::withMessages([
                        'cart' => 'Product variant does not exist.',
                    ]);
                }

                if ($variant->stock < $item->quantity) {
                    throw ValidationException::withMessages([
                        'cart' => "Not enough stock for product variant {$variant->id}.",
                    ]);
                }

                $unitPrice = (float) $variant->price;
                $unitDiscount = (float) ($variant->discount_price ?? 0);
                $unitFinalPrice = max($unitPrice - $unitDiscount, 0);

                $totalPrice += $unitPrice * $item->quantity;
                $totalDiscount += $unitDiscount * $item->quantity;
                $finalPrice += $unitFinalPrice * $item->quantity;

                $orderDetails[] = [
                    'product_variant_id' => $item->product_variant_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $unitPrice,
                    'unit_discount_price' => $unitDiscount,
                ];

                $variant->stock -= $item->quantity;
                $variant->save();
            }

            $order = Order::create([
                'user_id' => $user->id,
                'total_price' => $totalPrice,
                'discount_price' => $totalDiscount,
                'final_price' => $finalPrice,
                'status' => 'PENDING_PAYMENT',
                'ward_code' => $address->ward_code,
                'ward_name' => $address->ward_name,
                'province_id' => $address->province_id,
                'province_name' => $address->province_name,
                'specific_address' => $address->specific_address,
                'full_name' => $address->full_name,
                'phone' => $address->phone,
            ]);

            $order->orderDetails()->createMany($orderDetails);

            $order->payment()->create([
                'method' => $paymentMethod,
                'status' => 'UNPAID',
            ]);

            $cart->items()->delete();

            // Tạo đơn hàng ghn
            if (! config('services.ghn.shop_id')) {
                return $this->error('GHN shop id is not configured.', 500);
            }
            try {
                $token = config('services.ghn.token');

                if (! $token) {
                    throw new \RuntimeException('GHN token is not configured.');
                }

                $payload = [
                    'payment_type_id' => 1,
                    'required_note' => 'KHONGCHOXEMHANG',
                    'to_name' => $address->full_name,
                    'to_phone' => $address->phone,
                    'is_new_to_address' => true,
                    'to_address' => $address->specific_address,
                    'to_ward_code' => $address->ward_code,
                    'to_province_name' => $address->province_name,
                    'cod_amount' => $paymentMethod === 'COD' ? $finalPrice : 0,
                    'content' => "Giao hàng cho đơn hàng #{$order->id}",
                    'service_type_id' => 2,
                    'length' => 25,
                    'width' => 20,
                    'height' => 3,
                    'weight' => 300,
                ];

                $response = Http::baseUrl(config('services.ghn.base_url'))
                    ->withHeaders(['Token' => $token])
                    ->acceptJson()
                    ->withOptions([
                        'verify' => config('services.ghn.verify_ssl'),
                    ])
                    ->timeout(15)
                    ->post('/shiip/public-api/v2/shipping-order/create', $payload);

                $body = $response->json();
                if (! $response->successful() || ! isset($body['data']['order_code'])) {
                    throw new \RuntimeException('Failed to create order with GHN: '.($body['message'] ?? 'Unknown error'));
                }

                $orderCode = $body['data']['order_code'];
                $order->ghn_order_code = $orderCode;
                $order->save();

            } catch (\Exception $e) {
                throw ValidationException::withMessages([
                    'ghn' => 'Failed to create order with GHN: '.$e->getMessage(),
                ]);
            }

            return $order->load(['orderDetails.productVariant.product', 'payment']);
        });
    }

    public function getOrdersByUser(User $user, int $perPage = 10, int $page = 1)
    {
        return $user->orders()->with(['orderDetails.productVariant.product', 'payment'])->paginate($perPage, ['*'], 'page', $page);
    }

    public function getOrderById(User $user, int $orderId)
    {
        return $user->orders()->with(['orderDetails.productVariant.product', 'payment'])->findOrFail($orderId);
    }
}
