<?php

namespace App\Http\Controllers;

use App\Http\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class OrderController extends Controller
{
    private OrderService $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    #[OA\Post(
        path: '/api/order',
        operationId: 'createOrder',
        summary: 'Tạo đơn hàng từ giỏ hàng',
        description: 'Tạo đơn hàng từ giỏ hàng hiện tại của người dùng. Phần coupon/gift code chưa được áp dụng. Giá sản phẩm được tính theo công thức: giá gốc - giá giảm giá.',
        security: [['bearerAuth' => []]],
        tags: ['Đơn hàng'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['address_id'],
                properties: [
                    new OA\Property(property: 'address_id', type: 'integer', example: 1),
                    new OA\Property(property: 'gift_code', type: 'string', nullable: true, example: null),
                    new OA\Property(property: 'payment_method', type: 'string', enum: ['COD', 'VNPAY'], example: 'COD'),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Tạo đơn hàng thành công'),
            new OA\Response(response: 401, description: 'Chưa xác thực'),
            new OA\Response(response: 422, description: 'Dữ liệu không hợp lệ hoặc giỏ hàng trống'),
        ]
    )]
    public function store(Request $request)
    {
        $validated = $request->validate([
            'address_id' => [
                'required',
                'integer',
                Rule::exists('addresses', 'id')->where('user_id', $request->user()->id),
            ],
            'gift_code' => 'nullable|string',
            'payment_method' => 'nullable|in:COD,VNPAY',
        ]);

        $order = $this->orderService->createOrder(
            $request->user(),
            $validated['address_id'],
            $validated['payment_method'] ?? 'COD',
        );

        return response()->json([
            'status' => 201,
            'success' => true,
            'message' => null,
            'data' => [
                'items' => $order->toArray(),
                'pagination' => null,
            ],
        ], 201);
    }
}
