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
                        new OA\Property(property: 'message', type: 'string', example: 'Chưa xác thực.'),
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
                        new OA\Property(property: 'message', type: 'string', example: 'Dữ liệu không hợp lệ.'),
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
}
