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
            'data' => $order->toArray(),
        ], 201);
    }

    // Lấy danh sách đơn hàng của người dùng
    #[OA\Get(
        path: '/api/order',
        operationId: 'getUserOrders',
        summary: 'Lấy danh sách đơn hàng của người dùng',
        description: 'Trả về danh sách đơn hàng của người dùng đang đăng nhập, có phân trang và thông tin sản phẩm cơ bản trong từng đơn.',
        security: [['bearerAuth' => []]],
        tags: ['Đơn hàng'],
        parameters: [
            new OA\Parameter(
                name: 'page',
                description: 'Trang hiện tại.',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1),
                example: 1
            ),
            new OA\Parameter(
                name: 'per_page',
                description: 'Số đơn hàng trên mỗi trang.',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100),
                example: 10
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lấy danh sách đơn hàng thành công',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 200),
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', nullable: true, example: null),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(
                                    property: 'items',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'id', type: 'integer', example: 1),
                                            new OA\Property(property: 'status', type: 'string', example: 'PENDING_PAYMENT'),
                                            new OA\Property(property: 'final_price', type: 'number', format: 'float', example: 298000),
                                            new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-06-22T12:00:00.000000Z'),
                                            new OA\Property(property: 'full_name', type: 'string', example: 'Nguyễn Văn A'),
                                            new OA\Property(
                                                property: 'order_details',
                                                type: 'array',
                                                items: new OA\Items(
                                                    properties: [
                                                        new OA\Property(property: 'product_variant_id', type: 'integer', example: 1),
                                                        new OA\Property(property: 'quantity', type: 'integer', example: 2),
                                                        new OA\Property(property: 'image', type: 'string', nullable: true, example: 'products/ao-so-mi.jpg'),
                                                    ],
                                                    type: 'object'
                                                )
                                            ),
                                        ],
                                        type: 'object'
                                    )
                                ),
                                new OA\Property(
                                    property: 'pagination',
                                    properties: [
                                        new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                        new OA\Property(property: 'per_page', type: 'integer', example: 10),
                                        new OA\Property(property: 'total', type: 'integer', example: 25),
                                        new OA\Property(property: 'last_page', type: 'integer', example: 3),
                                    ],
                                    type: 'object'
                                ),
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
                description: 'Dữ liệu phân trang không hợp lệ',
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
    public function index(Request $request)
    {
        $validated = $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'status' => 'nullable|string|in:PENDING_PAYMENT,CONFIRMED,SHIPPING,COMPLETED,CANCELLED,RETURNED',
        ]);

        $orders = $this->orderService->getOrdersByUser(
            $request->user(),
            $validated['per_page'] ?? 10,
            $validated['page'] ?? 1,
            $validated['status'] ?? null
        );

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => [
                'items' => collect($orders->items())->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'status' => $order->status,
                        'final_price' => $order->final_price,
                        'created_at' => $order->created_at,
                        'full_name' => $order->full_name,
                        'order_details' => $order->orderDetails->map(function ($detail) {
                            return [
                                'product_variant_id' => $detail->product_variant_id,
                                'quantity' => $detail->quantity,
                                'image' => $detail->productVariant->image,
                            ];
                        }),
                    ];
                }),
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                    'last_page' => $orders->lastPage(),
                ],
            ],
        ]);
    }

    // Lấy chi tiết đơn hàng theo ID
    #[OA\Get(
        path: '/api/order/{order}',
        operationId: 'getOrderDetail',
        summary: 'Lấy chi tiết đơn hàng',
        description: 'Trả về chi tiết một đơn hàng của người dùng đang đăng nhập. Người dùng chỉ xem được đơn hàng thuộc tài khoản của mình.',
        security: [['bearerAuth' => []]],
        tags: ['Đơn hàng'],
        parameters: [
            new OA\Parameter(
                name: 'order',
                description: 'ID đơn hàng.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
                example: 1
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lấy chi tiết đơn hàng thành công',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 200),
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', nullable: true, example: null),
                        new OA\Property(
                            property: 'data',
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
                                            new OA\Property(
                                                property: 'product_variant',
                                                properties: [
                                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                                    new OA\Property(property: 'image', type: 'string', nullable: true, example: 'products/ao-so-mi.jpg'),
                                                    new OA\Property(
                                                        property: 'product',
                                                        properties: [
                                                            new OA\Property(property: 'id', type: 'integer', example: 1),
                                                            new OA\Property(property: 'name', type: 'string', example: 'Áo sơ mi'),
                                                        ],
                                                        type: 'object'
                                                    ),
                                                ],
                                                type: 'object'
                                            ),
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
                response: 404,
                description: 'Không tìm thấy đơn hàng hoặc đơn hàng không thuộc người dùng hiện tại',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 404),
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'No query results for model.'),
                        new OA\Property(property: 'data', type: 'object', nullable: true, example: null),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function show(Request $request, int $order)
    {

        $order = $this->orderService->getOrderById($request->user(), $order);

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => $order->toArray(),
        ]);
    }

    #[OA\Patch(
        path: '/api/order/{order}/cancel',
        operationId: 'cancelOrder',
        summary: 'Hủy đơn hàng',
        description: 'Hủy đơn hàng của người dùng đang đăng nhập. Chỉ các đơn ở trạng thái PENDING_PAYMENT hoặc CONFIRMED mới có thể hủy.',
        security: [['bearerAuth' => []]],
        tags: ['Đơn hàng'],
        parameters: [
            new OA\Parameter(
                name: 'order',
                description: 'ID đơn hàng cần hủy.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
                example: 1
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Hủy đơn hàng thành công',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 200),
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Đơn hàng đã được hủy thành công.'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'user_id', type: 'integer', example: 1),
                                new OA\Property(property: 'total_price', type: 'number', format: 'float', example: 398000),
                                new OA\Property(property: 'discount_price', type: 'number', format: 'float', example: 0),
                                new OA\Property(property: 'ship_price', type: 'number', format: 'float', example: 49500),
                                new OA\Property(property: 'discount_ship_price', type: 'number', format: 'float', example: 0),
                                new OA\Property(property: 'final_price', type: 'number', format: 'float', example: 447500),
                                new OA\Property(property: 'status', type: 'string', example: 'CANCELLED'),
                                new OA\Property(property: 'ghn_order_code', type: 'string', nullable: true, example: 'LX8E8H'),
                                new OA\Property(property: 'ward_code', type: 'string', example: '1003544'),
                                new OA\Property(property: 'ward_name', type: 'string', example: 'Phường An Khánh'),
                                new OA\Property(property: 'province_id', type: 'integer', example: 202),
                                new OA\Property(property: 'province_name', type: 'string', example: 'Hồ Chí Minh'),
                                new OA\Property(property: 'specific_address', type: 'string', example: '12 Nguyễn Văn A'),
                                new OA\Property(property: 'full_name', type: 'string', example: 'Nguyễn Văn A'),
                                new OA\Property(property: 'phone', type: 'string', example: '0901234567'),
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
                response: 404,
                description: 'Không tìm thấy đơn hàng hoặc đơn hàng không thuộc người dùng hiện tại',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 404),
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'No query results for model.'),
                        new OA\Property(property: 'data', type: 'object', nullable: true, example: null),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Đơn hàng không thể hủy ở trạng thái hiện tại',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 422),
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Order cannot be canceled at this stage.'),
                        new OA\Property(property: 'data', type: 'object', nullable: true, example: null),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function cancel(Request $request, int $order)
    {
        $order = $this->orderService->cancelOrder($request->user(), $order);

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => 'Đơn hàng đã được hủy thành công.',
            'data' => $order->toArray(),
        ]);
    }
}
