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
            new OA\Response(
                response: 201,
                description: 'Tạo đơn hàng thành công',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 201),
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', nullable: true, example: null),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(
                                    property: 'items',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 1),
                                        new OA\Property(property: 'user_id', type: 'integer', example: 1),
                                        new OA\Property(property: 'total_price', type: 'number', format: 'float', example: 398000),
                                        new OA\Property(property: 'discount_price', type: 'number', format: 'float', example: 100000),
                                        new OA\Property(property: 'final_price', type: 'number', format: 'float', example: 298000),
                                        new OA\Property(property: 'status', type: 'string', example: 'PENDING_PAYMENT'),
                                        new OA\Property(property: 'ghn_order_code', type: 'string', nullable: true, example: 'LJXX123456'),
                                        new OA\Property(property: 'ward_code', type: 'string', example: '1003544'),
                                        new OA\Property(property: 'ward_name', type: 'string', example: 'Phường An Khánh'),
                                        new OA\Property(property: 'province_id', type: 'integer', example: 202),
                                        new OA\Property(property: 'province_name', type: 'string', example: 'Hồ Chí Minh'),
                                        new OA\Property(property: 'specific_address', type: 'string', example: '12 Nguyễn Văn A'),
                                        new OA\Property(property: 'full_name', type: 'string', example: 'Nguyễn Văn A'),
                                        new OA\Property(property: 'phone', type: 'string', example: '0901234567'),
                                        new OA\Property(
                                            property: 'order_details',
                                            type: 'array',
                                            items: new OA\Items(
                                                properties: [
                                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                                    new OA\Property(property: 'product_variant_id', type: 'integer', example: 1),
                                                    new OA\Property(property: 'quantity', type: 'integer', example: 2),
                                                    new OA\Property(property: 'unit_price', type: 'number', format: 'float', example: 199000),
                                                    new OA\Property(property: 'unit_discount_price', type: 'number', format: 'float', nullable: true, example: 50000),
                                                ],
                                                type: 'object'
                                            )
                                        ),
                                        new OA\Property(
                                            property: 'payment',
                                            properties: [
                                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                                new OA\Property(property: 'method', type: 'string', example: 'COD'),
                                                new OA\Property(property: 'status', type: 'string', example: 'UNPAID'),
                                            ],
                                            type: 'object'
                                        ),
                                    ],
                                    type: 'object'
                                ),
                                new OA\Property(property: 'pagination', type: 'object', nullable: true, example: null),
                            ],
                            type: 'object'
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Chưa xác thực',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 401),
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthenticated.'),
                        new OA\Property(property: 'data', type: 'object', nullable: true, example: null),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Dữ liệu không hợp lệ hoặc giỏ hàng trống',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 422),
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'The given data was invalid.'),
                        new OA\Property(property: 'data', type: 'object', nullable: true, example: null),
                    ],
                    type: 'object'
                )
            ),
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
