<?php

namespace App\Http\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

class GhnReturnService
{
    public function create(Order $order): array
    {
        $token = trim((string) config('services.ghn.token'));
        $shopId = trim((string) config('services.ghn.shop_id'));
        $shop = config('services.ghn.return_shop', []);

        if ($token === '' || $shopId === '') {
            throw ValidationException::withMessages([
                'ghn' => 'GHN token or shop id is not configured.',
            ]);
        }

        foreach (['name', 'phone', 'address', 'ward_code', 'province_name'] as $field) {
            if (trim((string) ($shop[$field] ?? '')) === '') {
                throw ValidationException::withMessages([
                    'ghn' => "GHN return shop {$field} is not configured.",
                ]);
            }
        }

        $payload = [
            'payment_type_id' => 2,
            'required_note' => 'KHONGCHOXEMHANG',
            'from_name' => $order->full_name,
            'from_phone' => $order->phone,
            'is_new_from_address' => true,
            'from_address' => $order->specific_address,
            'from_ward_code' => (string) $order->ward_code,
            'from_province_name' => $order->province_name,
            'to_name' => $shop['name'],
            'to_phone' => $shop['phone'],
            'is_new_to_address' => true,
            'to_address' => $shop['address'],
            'to_ward_code' => (string) $shop['ward_code'],
            'to_province_name' => $shop['province_name'],
            'cod_amount' => 0,
            'content' => sprintf('Hoàn hàng cho đơn hàng #%06d', $order->id),
            'service_type_id' => (int) config('services.ghn.default_service_type_id', 2),
            'length' => (int) config('services.ghn.default_length', 25),
            'width' => (int) config('services.ghn.default_width', 20),
            'height' => (int) config('services.ghn.default_height', 3),
            'weight' => (int) config('services.ghn.default_weight', 300),
        ];

        try {
            $response = Http::baseUrl(config('services.ghn.base_url'))
                ->withHeaders([
                    'Token' => $token,
                    'ShopId' => $shopId,
                ])
                ->acceptJson()
                ->withOptions(['verify' => config('services.ghn.verify_ssl')])
                ->timeout(15)
                ->post('/shiip/public-api/v2/shipping-order/create', $payload);
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'ghn' => 'Cannot connect to GHN: '.$exception->getMessage(),
            ]);
        }

        $body = $response->json();

        if (
            ! $response->successful()
            || ($body['code'] ?? null) !== 200
            || empty($body['data']['order_code'])
        ) {
            throw ValidationException::withMessages([
                'ghn' => $body['message_display']
                    ?? $body['message']
                    ?? 'Failed to create the GHN return shipment.',
            ]);
        }

        return $body;
    }
}
