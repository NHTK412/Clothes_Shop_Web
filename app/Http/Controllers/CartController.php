<?php

namespace App\Http\Controllers;

use App\Http\Services\CartService;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CartController extends Controller
{
    private CartService $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    #[OA\Get(
        path: '/api/cart/items',
        operationId: 'getCurrentCartItems',
        summary: 'Lấy danh sách sản phẩm trong giỏ hàng hiện tại',
        description: 'Trả về danh sách sản phẩm trong giỏ hàng của người dùng đã đăng nhập, bao gồm tên sản phẩm, thuộc tính biến thể, hình ảnh, giá gốc và giá giảm.',
        security: [['bearerAuth' => []]],
        tags: ['Giỏ hàng'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lấy danh sách sản phẩm trong giỏ hàng thành công',
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
                                            new OA\Property(property: 'cart_item_id', type: 'integer', example: 1),
                                            new OA\Property(property: 'product_variant_id', type: 'integer', example: 1),
                                            new OA\Property(property: 'product_name', type: 'string', example: 'Áo thun basic'),
                                            new OA\Property(
                                                property: 'attributes',
                                                type: 'array',
                                                items: new OA\Items(
                                                    properties: [
                                                        new OA\Property(property: 'type', type: 'string', example: 'color'),
                                                        new OA\Property(property: 'display_type', type: 'string', example: 'Màu sắc'),
                                                        new OA\Property(property: 'value', type: 'string', example: 'black'),
                                                        new OA\Property(property: 'display_value', type: 'string', example: 'Đen'),
                                                    ],
                                                    type: 'object'
                                                )
                                            ),
                                            new OA\Property(property: 'image', type: 'string', nullable: true, example: 'products/ao-thun-den.jpg'),
                                            new OA\Property(property: 'original_price', type: 'number', format: 'float', example: 199000),
                                            new OA\Property(property: 'discount_price', type: 'number', format: 'float', nullable: true, example: 149000),
                                            new OA\Property(property: 'quantity', type: 'integer', example: 2),
                                        ],
                                        type: 'object'
                                    )
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
        ]
    )]
    public function getItems(Request $request)
    {
        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => [
                'items' => $this->cartService->getItems($request->user()),
                'pagination' => null,
            ],
        ], 200);
    }

    #[OA\Post(
        path: '/api/cart/items',
        operationId: 'addCartItem',
        summary: 'Thêm sản phẩm vào giỏ hàng',
        description: 'Tự tạo giỏ hàng cho người dùng đã đăng nhập nếu chưa có. Nếu biến thể sản phẩm đã tồn tại trong giỏ hàng, hệ thống sẽ cộng thêm số lượng.',
        security: [['bearerAuth' => []]],
        tags: ['Giỏ hàng'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['product_variant_id'],
                properties: [
                    new OA\Property(
                        property: 'product_variant_id',
                        description: 'ID của biến thể sản phẩm cần thêm vào giỏ hàng.',
                        type: 'integer',
                        example: 1
                    ),
                    new OA\Property(
                        property: 'quantity',
                        description: 'Số lượng cần thêm. Mặc định là 1.',
                        type: 'integer',
                        minimum: 1,
                        example: 1
                    ),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Cập nhật số lượng sản phẩm trong giỏ hàng thành công',
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
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 1),
                                        new OA\Property(property: 'user_id', type: 'integer', example: 1),
                                        new OA\Property(
                                            property: 'items',
                                            type: 'array',
                                            items: new OA\Items(
                                                properties: [
                                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                                    new OA\Property(property: 'cart_id', type: 'integer', example: 1),
                                                    new OA\Property(property: 'product_variant_id', type: 'integer', example: 1),
                                                    new OA\Property(property: 'quantity', type: 'integer', example: 2),
                                                ],
                                                type: 'object'
                                            )
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
                response: 201,
                description: 'Thêm sản phẩm vào giỏ hàng thành công',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 201),
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', nullable: true, example: null),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'items', type: 'object'),
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
                description: 'Dữ liệu không hợp lệ hoặc không đủ tồn kho',
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
    public function addItem(Request $request)
    {
        $validated = $request->validate([
            'product_variant_id' => 'required|integer|exists:product_variants,id',
            'quantity' => 'nullable|integer|min:1',
        ]);

        $quantity = $validated['quantity'] ?? 1;
        [$cart, $created] = $this->cartService->addItem(
            $request->user(),
            $validated['product_variant_id'],
            $quantity
        );

        $statusCode = $created ? 201 : 200;

        return response()->json([
            'status' => $statusCode,
            'success' => true,
            'message' => null,
            'data' => [
                'items' => $cart->toArray(),
                'pagination' => null,
            ],
        ], $statusCode);
    }

    #[OA\Put(
        path: '/api/cart/items/{cartItem}',
        operationId: 'updateCartItem',
        summary: 'Cập nhật số lượng sản phẩm trong giỏ hàng',
        description: 'Cập nhật số lượng của một sản phẩm trong giỏ hàng hiện tại. Nếu số lượng nhỏ hơn hoặc bằng 0, sản phẩm sẽ bị xóa khỏi giỏ hàng. Nếu số lượng vượt quá tồn kho hiện tại, API trả lỗi 422.',
        security: [['bearerAuth' => []]],
        tags: ['Giỏ hàng'],
        parameters: [
            new OA\Parameter(
                name: 'cartItem',
                description: 'ID của item trong giỏ hàng.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer'),
                example: 1
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['quantity'],
                properties: [
                    new OA\Property(
                        property: 'quantity',
                        description: 'Số lượng mới. Nhỏ hơn hoặc bằng 0 sẽ xóa sản phẩm khỏi giỏ hàng.',
                        type: 'integer',
                        example: 2
                    ),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Cập nhật giỏ hàng thành công',
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
                                            new OA\Property(property: 'cart_item_id', type: 'integer', example: 1),
                                            new OA\Property(property: 'product_variant_id', type: 'integer', example: 1),
                                            new OA\Property(property: 'product_name', type: 'string', example: 'Áo thun basic'),
                                            new OA\Property(property: 'image', type: 'string', nullable: true, example: 'products/ao-thun-den.jpg'),
                                            new OA\Property(property: 'original_price', type: 'number', format: 'float', example: 199000),
                                            new OA\Property(property: 'discount_price', type: 'number', format: 'float', nullable: true, example: 149000),
                                            new OA\Property(property: 'quantity', type: 'integer', example: 2),
                                        ],
                                        type: 'object'
                                    )
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
                response: 404,
                description: 'Không tìm thấy sản phẩm trong giỏ hàng',
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
                description: 'Dữ liệu không hợp lệ hoặc vượt quá tồn kho hiện tại',
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
    public function updateItem(Request $request, int $cartItem)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer',
        ]);

        $this->cartService->updateItem(
            $request->user(),
            $cartItem,
            $validated['quantity']
        );

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => [
                'items' => $this->cartService->getItems($request->user()),
                'pagination' => null,
            ],
        ], 200);
    }

    #[OA\Get(
        path: '/api/cart/items/count',
        operationId: 'getCurrentCartItemCount',
        summary: 'Lấy số lượng sản phẩm trong giỏ hàng hiện tại',
        description: 'Trả về tổng số lượng sản phẩm trong giỏ hàng của người dùng đã đăng nhập. Nếu người dùng chưa có giỏ hàng, count trả về 0.',
        security: [['bearerAuth' => []]],
        tags: ['Giỏ hàng'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lấy số lượng sản phẩm trong giỏ hàng thành công',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 200),
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', nullable: true, example: null),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'count', type: 'integer', example: 3),
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
        ]
    )]
    public function getCountItem(Request $request)
    {
        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => [
                'count' => $this->cartService->getItemCount($request->user()),
            ],
        ], 200);
    }
}
