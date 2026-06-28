<?php

namespace App\Http\Controllers;

use App\Http\Services\PromotionService;
use App\Models\Promotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PromotionController extends Controller
{
    private PromotionService $promotionService;

    public function __construct(PromotionService $promotionService)
    {
        $this->promotionService = $promotionService;
    }

    #[OA\Get(
        path: '/api/promotion',
        summary: 'Danh sách khuyến mãi',
        description: 'Lấy danh sách khuyến mãi có phân trang, tìm kiếm và lọc trạng thái. Kết quả bao gồm các sản phẩm thuộc từng khuyến mãi.',
        tags: ['Khuyến mãi'],
        parameters: [
            new OA\Parameter(
                name: 'page',
                description: 'Trang hiện tại.',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, default: 1),
                example: 1
            ),
            new OA\Parameter(
                name: 'per_page',
                description: 'Số khuyến mãi trên mỗi trang, tối đa 100.',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20),
                example: 20
            ),
            new OA\Parameter(
                name: 'q',
                description: 'Tìm kiếm theo tên hoặc mô tả khuyến mãi.',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', maxLength: 255),
                example: 'mùa hè'
            ),
            new OA\Parameter(
                name: 'is_active',
                description: 'Lọc theo trạng thái bật hoặc tắt của khuyến mãi.',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'boolean'),
                example: true
            ),
            new OA\Parameter(
                name: 'status',
                description: 'Lọc theo thời gian diễn ra khuyến mãi.',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', enum: ['upcoming', 'active', 'expired']),
                example: 'active'
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lấy danh sách khuyến mãi thành công',
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
                                            new OA\Property(property: 'name', type: 'string', example: 'Khuyến mãi mùa hè'),
                                            new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Giảm giá các sản phẩm mùa hè'),
                                            new OA\Property(property: 'discount_amount', type: 'number', format: 'float', example: 20),
                                            new OA\Property(property: 'discount_type', type: 'string', enum: ['percentage', 'fixed'], example: 'percentage'),
                                            new OA\Property(property: 'start_date', type: 'string', format: 'date-time', example: '2026-06-01T00:00:00.000000Z'),
                                            new OA\Property(property: 'end_date', type: 'string', format: 'date-time', example: '2026-06-30T23:59:59.000000Z'),
                                            new OA\Property(property: 'is_active', type: 'boolean', example: true),
                                            new OA\Property(
                                                property: 'products',
                                                type: 'array',
                                                items: new OA\Items(
                                                    properties: [
                                                        new OA\Property(property: 'id', type: 'integer', example: 10),
                                                        new OA\Property(property: 'name', type: 'string', example: 'Áo thun basic'),
                                                        new OA\Property(property: 'price', type: 'number', format: 'float', example: 199000),
                                                        new OA\Property(property: 'discount_price', type: 'number', format: 'float', nullable: true, example: 159200),
                                                        new OA\Property(property: 'image', type: 'string', nullable: true, example: 'https://cdn.example.com/products/ao-thun.jpg'),
                                                    ],
                                                    type: 'object'
                                                )
                                            ),
                                            new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
                                            new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
                                        ],
                                        type: 'object'
                                    )
                                ),
                                new OA\Property(
                                    property: 'pagination',
                                    properties: [
                                        new OA\Property(property: 'page', type: 'integer', example: 1),
                                        new OA\Property(property: 'limit', type: 'integer', example: 20),
                                        new OA\Property(property: 'totalItems', type: 'integer', example: 25),
                                        new OA\Property(property: 'totalPages', type: 'integer', example: 2),
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
                response: 422,
                description: 'Tham số lọc không hợp lệ',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 422),
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'The selected status is invalid.'),
                        new OA\Property(property: 'data', type: 'object', nullable: true, example: null),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        if ($request->has('is_active')) {
            $isActive = $request->input('is_active');

            if (in_array($isActive, ['true', '1', 1, true], true)) {
                $request->merge(['is_active' => true]);
            } elseif (in_array($isActive, ['false', '0', 0, false], true)) {
                $request->merge(['is_active' => false]);
            }
        }

        $filters = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'q' => 'sometimes|nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
            'status' => 'sometimes|in:upcoming,active,expired',
        ]);

        $perPage = (int) ($filters['per_page'] ?? 20);
        $page = (int) ($filters['page'] ?? 1);
        $now = now();

        $query = Promotion::query()
            ->with([
                'products:id,name,price,discount_price,image',
            ])
            ->when(! empty($filters['q']), function ($query) use ($filters) {
                $keyword = $filters['q'];

                $query->where(function ($subQuery) use ($keyword) {
                    $subQuery->where('name', 'like', "%{$keyword}%")
                        ->orWhere('description', 'like', "%{$keyword}%");
                });
            })
            ->when(array_key_exists('is_active', $filters), function ($query) use ($filters) {
                $query->where('is_active', $filters['is_active']);
            })
            ->when(($filters['status'] ?? null) === 'upcoming', function ($query) use ($now) {
                $query->where('start_date', '>', $now);
            })
            ->when(($filters['status'] ?? null) === 'active', function ($query) use ($now) {
                $query->where('is_active', true)
                    ->where('start_date', '<=', $now)
                    ->where('end_date', '>=', $now);
            })
            ->when(($filters['status'] ?? null) === 'expired', function ($query) use ($now) {
                $query->where('end_date', '<', $now);
            })
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => [
                'items' => $query->items(),
                'pagination' => [
                    'page' => $query->currentPage(),
                    'limit' => $query->perPage(),
                    'totalItems' => $query->total(),
                    'totalPages' => $query->lastPage(),
                ],
            ],
        ]);
    }

    public function first()
    {
        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => $this->promotionService->first(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'discount_amount' => 'required|numeric|gt:0',
            'discount_type' => 'required|in:percentage,fixed',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_active' => 'sometimes|boolean',
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'required|integer|distinct|exists:products,id',
        ]);

        return response()->json([
            'status' => 201,
            'success' => true,
            'message' => null,
            'data' => $this->promotionService->create($data),
        ], 201);
    }

    public function update(Request $request, Promotion $promotion): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'discount_amount' => 'sometimes|required|numeric|gt:0',
            'discount_type' => 'sometimes|required|in:percentage,fixed',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date',
            'is_active' => 'sometimes|boolean',
            'product_ids' => 'sometimes|required|array|min:1',
            'product_ids.*' => 'required|integer|distinct|exists:products,id',
        ]);

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => $this->promotionService->update($promotion, $data),
        ]);
    }

    public function destroy(Promotion $promotion): JsonResponse
    {
        $this->promotionService->delete($promotion);

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => true,
        ]);
    }
}
