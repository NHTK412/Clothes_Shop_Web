<?php

namespace App\Http\Controllers;

use App\Http\Services\OrderService;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;
use Throwable;

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
            'gift_code' => [
                'nullable',
                'string',
                Rule::exists('vouchers', 'code')->where(function ($query) {
                    $query->where('is_active', true)
                        ->where('expiry_date', '>=', now());
                }),
            ],
            'payment_method' => 'nullable|in:COD,VNPAY',
        ]);

        $order = $this->orderService->createOrder(
            $request->user(),
            $validated['address_id'],
            $validated['payment_method'] ?? 'COD',
            $validated['gift_code'] ?? null
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

    #[OA\Get(
        path: '/api/admin/orders',
        operationId: 'adminListOrders',
        summary: 'Danh sách đơn hàng cho quản trị viên',
        description: 'Lấy toàn bộ đơn hàng trong hệ thống cho quản trị viên, hỗ trợ tìm kiếm, lọc theo trạng thái và phân trang.',
        security: [['bearerAuth' => []]],
        tags: ['Đơn hàng'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', description: 'Tìm theo tên, email hoặc số điện thoại khách hàng.', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', description: 'Lọc theo trạng thái đơn hàng.', required: false, schema: new OA\Schema(type: 'string', enum: ['PENDING_PAYMENT', 'CONFIRMED', 'SHIPPING', 'COMPLETED', 'CANCELLED', 'RETURNED'])),
            new OA\Parameter(name: 'page', in: 'query', description: 'Trang hiện tại.', required: false, schema: new OA\Schema(type: 'integer', minimum: 1), example: 1),
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Số đơn hàng mỗi trang.', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100), example: 15),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lấy danh sách đơn hàng thành công'),
            new OA\Response(response: 401, description: 'Chưa xác thực'),
            new OA\Response(response: 403, description: 'Chỉ quản trị viên được phép'),
        ]
    )]
    public function adminIndex(Request $request)
    {
        $this->ensureAdmin($request->user());

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => ['nullable', 'string', function ($attribute, $value, $fail) {
                if ($value !== null && $this->normalizeOrderStatus($value) === null) {
                    $fail('Trạng thái không hợp lệ.');
                }
            }],
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Order::query()->with(['user', 'payment', 'orderDetails.productVariant.product']);

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('email', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
            });
        }

        if (! empty($validated['status'])) {
            $query->where('status', $this->normalizeOrderStatus($validated['status']));
        }

        $orders = $query->orderByDesc('created_at')->paginate($validated['per_page'] ?? 15, ['*'], 'page', $validated['page'] ?? 1);

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => [
                'data' => $orders->getCollection()->map(fn (Order $order) => $this->formatOrderForAdminList($order)),
                'pagination' => [
                    'current_page' => $orders->currentPage(),
                    'per_page' => $orders->perPage(),
                    'total' => $orders->total(),
                    'last_page' => $orders->lastPage(),
                ],
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/admin/orders/{order}',
        operationId: 'adminShowOrder',
        summary: 'Chi tiết đơn hàng cho quản trị viên',
        description: 'Xem toàn bộ thông tin một đơn hàng trong hệ thống dành cho quản trị viên.',
        security: [['bearerAuth' => []]],
        tags: ['Đơn hàng'],
        parameters: [
            new OA\Parameter(name: 'order', in: 'path', description: 'ID đơn hàng.', required: true, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lấy thông tin đơn hàng thành công'),
            new OA\Response(response: 401, description: 'Chưa xác thực'),
            new OA\Response(response: 403, description: 'Chỉ quản trị viên được phép'),
            new OA\Response(response: 404, description: 'Không tìm thấy đơn hàng'),
        ]
    )]
    public function adminShow(Request $request, Order $order)
    {
        $this->ensureAdmin($request->user());

        $order->load(['user', 'payment', 'orderDetails.productVariant.product']);

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => $this->formatOrderForAdminDetail($order),
        ]);
    }

    #[OA\Get(
        path: '/api/admin/orders/summary',
        operationId: 'adminOrderSummary',
        summary: 'Tổng kết doanh thu đơn hàng',
        description: 'Lấy thống kê tổng doanh thu từ các đơn hàng đã hoàn thành, hỗ trợ lọc theo khoảng thời gian.',
        security: [['bearerAuth' => []]],
        tags: ['Đơn hàng'],
        parameters: [
            new OA\Parameter(name: 'from_date', in: 'query', description: 'Ngày bắt đầu (YYYY-MM-DD)', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'to_date', in: 'query', description: 'Ngày kết thúc (YYYY-MM-DD)', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lấy thống kê thành công',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 200),
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', nullable: true, example: null),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'total_revenue', type: 'number', format: 'float', example: 50000000),
                                new OA\Property(property: 'total_orders', type: 'integer', example: 150),
                                new OA\Property(property: 'average_order_value', type: 'number', format: 'float', example: 333333.33),
                                new OA\Property(property: 'from_date', type: 'string', format: 'date', example: '2026-06-01'),
                                new OA\Property(property: 'to_date', type: 'string', format: 'date', example: '2026-06-28'),
                            ],
                            type: 'object'
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Chưa xác thực'),
            new OA\Response(response: 403, description: 'Chỉ quản trị viên được phép'),
        ]
    )]
    public function adminOrderSummary(Request $request)
    {
        $this->ensureAdmin($request->user());

        $validated = $request->validate([
            'from_date' => 'nullable|date_format:Y-m-d',
            'to_date' => 'nullable|date_format:Y-m-d',
        ]);

        $query = Order::query()->where('status', 'COMPLETED');

        $fromDate = $validated['from_date'] ?? null;
        $toDate = $validated['to_date'] ?? null;

        if ($fromDate) {
            $query->whereDate('created_at', '>=', $fromDate);
        }

        if ($toDate) {
            $query->whereDate('created_at', '<=', $toDate);
        }

        $totalRevenue = $query->sum('final_price');
        $totalOrders = $query->count();
        $averageOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => [
                'total_revenue' => (float) $totalRevenue,
                'total_orders' => (int) $totalOrders,
                'average_order_value' => (float) $averageOrderValue,
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ],
        ]);
    }

    // Lấy danh sách đơn hàng của khách hàng (admin)
    #[OA\Get(
        path: '/api/admin/customers/{customer}/orders',
        operationId: 'adminOrdersByCustomer',
        summary: 'Lấy danh sách đơn hàng của khách hàng',
        description: 'Lấy danh sách tất cả đơn hàng của một khách hàng cụ thể, hỗ trợ lọc theo trạng thái và phân trang.',
        security: [['bearerAuth' => []]],
        tags: ['Đơn hàng'],
        parameters: [
            new OA\Parameter(
                name: 'customer',
                description: 'ID khách hàng.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
                example: 3
            ),
            new OA\Parameter(name: 'status', in: 'query', description: 'Lọc theo trạng thái đơn hàng', required: false, schema: new OA\Schema(type: 'string', enum: ['PENDING_PAYMENT', 'CONFIRMED', 'SHIPPING', 'COMPLETED', 'CANCELLED', 'RETURNED'])),
            new OA\Parameter(name: 'page', in: 'query', description: 'Số trang (mặc định 1)', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Số bản ghi trên trang (mặc định 15, tối đa 100)', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lấy danh sách thành công',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 200),
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', nullable: true, example: null),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
                                new OA\Property(
                                    property: 'pagination',
                                    properties: [
                                        new OA\Property(property: 'current_page', type: 'integer'),
                                        new OA\Property(property: 'per_page', type: 'integer'),
                                        new OA\Property(property: 'total', type: 'integer'),
                                        new OA\Property(property: 'last_page', type: 'integer'),
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
            new OA\Response(response: 401, description: 'Chưa xác thực'),
            new OA\Response(response: 403, description: 'Chỉ quản trị viên được phép'),
        ]
    )]
    public function adminOrdersByCustomer(Request $request, User $customer)
    {
        $this->ensureAdmin($request->user());

        $validated = $request->validate([
            'status' => ['nullable', 'string', function ($attribute, $value, $fail) {
                if ($value !== null && $this->normalizeOrderStatus($value) === null) {
                    $fail('Trạng thái không hợp lệ.');
                }
            }],
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = Order::query()->where('user_id', $customer->id)->with(['payment', 'orderDetails.productVariant.product']);

        if (! empty($validated['status'])) {
            $query->where('status', $this->normalizeOrderStatus($validated['status']));
        }

        $orders = $query->orderByDesc('created_at')->paginate($validated['per_page'] ?? 15, ['*'], 'page', $validated['page'] ?? 1);

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => [
                'data' => $orders->getCollection()->map(fn (Order $order) => $this->formatOrderForAdminList($order)),
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

    public function review(Request $request, int $order, int $orderDetail)
    {
        $validated = $request->validate([
            'order' => [
                'required',
                'integer',
                Rule::exists('orders', 'id')->where('user_id', $request->user()->id),
            ],
            'orderDetail' => [
                'required',
                'integer',
                Rule::exists('order_details', 'id')->where('order_id', $order),
            ],
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            'imagePaths' => 'nullable|array|max:5',
        ]);

        $review = $this->orderService->reviewOrderDetail($request->user(), $order, $orderDetail, $validated['rating'], $validated['comment'] ?? null, $validated['imagePaths'] ?? []);

        return response()->json([
            'status' => 201,
            'success' => true,
            'message' => 'Đánh giá sản phẩm thành công.',
            'data' => $review
        ], 201);
    }


    #[OA\Post(
        path: '/api/ghn/webhook/order-status',
        operationId: 'updateOrderStatusFromGhnWebhook',
        summary: 'Webhook cập nhật trạng thái đơn hàng từ GHN',
        description: 'Endpoint để GHN thông báo đơn hàng có thay đổi. GHN gửi kèm token do hệ thống cung cấp và mã vận đơn. Sau khi xác thực token, hệ thống gọi API chi tiết đơn hàng GHN để lấy status hiện tại rồi cập nhật trạng thái nội bộ: picked -> SHIPPING, delivered -> COMPLETED, return -> RETURNED.',
        tags: ['GHN'],
        parameters: [
            new OA\Parameter(
                name: 'X-GHN-Webhook-Token',
                description: 'Token webhook do hệ thống cung cấp cho GHN. Có thể gửi bằng header này, X-Webhook-Token, Authorization: Bearer token, hoặc field token trong body.',
                in: 'header',
                required: true,
                schema: new OA\Schema(type: 'string'),
                example: 'webhook-secret-token'
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['order_code'],
                properties: [
                    new OA\Property(property: 'order_code', type: 'string', example: '5E3NK3RS'),
                    new OA\Property(property: 'token', type: 'string', nullable: true, example: 'webhook-secret-token'),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Cập nhật trạng thái đơn hàng thành công hoặc webhook status không cần cập nhật',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 200),
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', nullable: true, example: null),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'ghn_status', type: 'string', example: 'picked'),
                                new OA\Property(property: 'old_status', type: 'string', example: 'CONFIRMED'),
                                new OA\Property(property: 'new_status', type: 'string', example: 'SHIPPING'),
                                new OA\Property(property: 'status_changed', type: 'boolean', example: true),
                                new OA\Property(
                                    property: 'order',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 1),
                                        new OA\Property(property: 'user_id', type: 'integer', example: 1),
                                        new OA\Property(property: 'status', type: 'string', example: 'SHIPPING'),
                                        new OA\Property(property: 'ghn_order_code', type: 'string', example: '5E3NK3RS'),
                                        new OA\Property(property: 'total_price', type: 'number', format: 'float', example: 300000),
                                        new OA\Property(property: 'discount_price', type: 'number', format: 'float', example: 0),
                                        new OA\Property(property: 'ship_price', type: 'number', format: 'float', example: 30000),
                                        new OA\Property(property: 'discount_ship_price', type: 'number', format: 'float', example: 0),
                                        new OA\Property(property: 'final_price', type: 'number', format: 'float', example: 330000),
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
                response: 404,
                description: 'Không tìm thấy đơn hàng theo mã vận đơn GHN',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'No query results for model.'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Thiếu mã vận đơn GHN',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 422),
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Missing GHN order code.'),
                        new OA\Property(property: 'data', type: 'object', nullable: true, example: null),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Token webhook không hợp lệ',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 401),
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Invalid GHN webhook token.'),
                        new OA\Property(property: 'data', type: 'object', nullable: true, example: null),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    private function ensureAdmin(?User $user): void
    {
        if (! $user || $user->role !== 'ROLE_ADMIN') {
            abort(403, 'Chỉ quản trị viên được phép');
        }
    }

    private function getAllowedOrderStatuses(): array
    {
        return ['pending', 'processing', 'completed', 'cancelled', 'returned'];
    }

    private function normalizeOrderStatus(?string $status): ?string
    {
        if (! $status) {
            return null;
        }

        $normalized = strtolower($status);

        return in_array($normalized, $this->getAllowedOrderStatuses(), true) ? $normalized : null;
    }

    private function formatOrderForAdminList(Order $order): array
    {
        return [
            'id' => $order->id,
            'user_id' => $order->user_id,
            'customer' => [
                'id' => $order->user?->id,
                'name' => $order->user?->name,
                'email' => $order->user?->email,
                'phone' => $order->user?->phone,
            ],
            'status' => $order->status,
            'total_price' => $order->total_price,
            'discount_price' => $order->discount_price,
            'final_price' => $order->final_price,
            'payment_status' => $order->payment?->status,
            'payment_method' => $order->payment?->method,
            'created_at' => $order->created_at?->toISOString(),
            'item_count' => $order->orderDetails->count(),
        ];
    }

    private function formatOrderForAdminDetail(Order $order): array
    {
        return [
            'id' => $order->id,
            'user_id' => $order->user_id,
            'customer' => [
                'id' => $order->user?->id,
                'name' => $order->user?->name,
                'email' => $order->user?->email,
                'phone' => $order->user?->phone,
            ],
            'status' => $order->status,
            'total_price' => $order->total_price,
            'discount_price' => $order->discount_price,
            'ship_price' => $order->ship_price,
            'discount_ship_price' => $order->discount_ship_price,
            'final_price' => $order->final_price,
            'ghn_order_code' => $order->ghn_order_code,
            'shipping_address' => [
                'ward_code' => $order->ward_code,
                'ward_name' => $order->ward_name,
                'province_id' => $order->province_id,
                'province_name' => $order->province_name,
                'specific_address' => $order->specific_address,
                'full_name' => $order->full_name,
                'phone' => $order->phone,
            ],
            'payment' => $order->payment ? [
                'id' => $order->payment->id,
                'method' => $order->payment->method,
                'status' => $order->payment->status,
            ] : null,
            'order_details' => $order->orderDetails->map(fn ($detail) => [
                'id' => $detail->id,
                'product_variant_id' => $detail->product_variant_id,
                'product_name' => $detail->productVariant?->product?->name,
                'variant_image' => $detail->productVariant?->image,
                'quantity' => $detail->quantity,
                'unit_price' => $detail->unit_price,
                'unit_discount_price' => $detail->unit_discount_price,
            ])->values(),
            'created_at' => $order->created_at?->toISOString(),
            'updated_at' => $order->updated_at?->toISOString(),
        ];
    }

    public function update(Request $request)
    {
        $payload = $request->all();
        $configuredWebhookToken = config('services.ghn.webhook_token');
        $incomingToken = $request->bearerToken()
            ?? $request->header('X-GHN-Webhook-Token')
            ?? $request->header('X-Webhook-Token')
            ?? $payload['token']
            ?? $payload['webhook_token']
            ?? data_get($payload, 'data.token')
            ?? data_get($payload, 'data.webhook_token');

        if (! $configuredWebhookToken) {
            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'GHN webhook token is not configured.',
                'data' => null,
            ], 500);
        }

        if (! $incomingToken || ! hash_equals((string) $configuredWebhookToken, (string) $incomingToken)) {
            return response()->json([
                'status' => 401,
                'success' => false,
                'message' => 'Invalid GHN webhook token.',
                'data' => null,
            ], 401);
        }

        $orderCode = $payload['order_code']
            ?? $payload['OrderCode']
            ?? $payload['orderCode']
            ?? data_get($payload, 'data.order_code')
            ?? data_get($payload, 'data.OrderCode');

        if (! $orderCode) {
            return response()->json([
                'status' => 422,
                'success' => false,
                'message' => 'Missing GHN order code.',
                'data' => null,
            ], 422);
        }

        $order = Order::where('ghn_order_code', $orderCode)->firstOrFail();

        $ghnToken = config('services.ghn.token');

        if (! $ghnToken) {
            return response()->json([
                'status' => 500,
                'success' => false,
                'message' => 'GHN token is not configured.',
                'data' => null,
            ], 500);
        }

        try {
            $response = Http::baseUrl(config('services.ghn.base_url'))
                ->withHeaders(['Token' => $ghnToken])
                ->acceptJson()
                ->withOptions([
                    'verify' => config('services.ghn.verify_ssl'),
                ])
                ->timeout(15)
                ->get('/shiip/public-api/v2/shipping-order/detail', [
                    'order_code' => $orderCode,
                ]);
        } catch (Throwable $exception) {
            return response()->json([
                'status' => 502,
                'success' => false,
                'message' => $exception->getMessage(),
                'data' => null,
            ], 502);
        }

        $body = $response->json();

        if (! $response->successful() || ($body['code'] ?? null) !== 200) {
            $status = $response->successful() ? 400 : $response->status();

            return response()->json([
                'status' => $status,
                'success' => false,
                'message' => $body['message'] ?? 'GHN request failed.',
                'data' => null,
            ], $status);
        }

        $ghnStatus = $body['data']['status'] ?? null;
        $oldStatus = $order->status;

        $statusMap = [
            // Nếu là đã tới lấy hàng thì cập nhật thành đang giao
            'picked' => 'SHIPPING',

            // Nếu là đã giao hàng thì cập nhật thành hoàn thành
            'delivered' => 'COMPLETED',

            // Nếu là trả hàng thì cập nhật thành trả hàng
            'return' => 'RETURNED',
        ];

        if (isset($statusMap[$ghnStatus]) && $order->status !== $statusMap[$ghnStatus]) {
            $order->status = $statusMap[$ghnStatus];
            $order->save();
        }

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => [
                'ghn_status' => $ghnStatus,
                'old_status' => $oldStatus,
                'new_status' => $order->status,
                'status_changed' => $oldStatus !== $order->status,
                'order' => $order->fresh()->toArray(),
            ],
        ]);
    }
}
