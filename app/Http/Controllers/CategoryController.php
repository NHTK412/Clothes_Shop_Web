<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'CategorySummary',
    required: ['id', 'name', 'image'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Áo thun'),
        new OA\Property(property: 'image', type: 'string', format: 'uri', example: 'https://cdn.example.com/categories/ao-thun.jpg'),
        new OA\Property(property: 'parent_id', type: 'integer', nullable: true, example: null),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'CategoryDetail',
    required: ['id', 'name', 'image'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Áo thun'),
        new OA\Property(property: 'image', type: 'string', format: 'uri', example: 'https://cdn.example.com/categories/ao-thun.jpg'),
        new OA\Property(property: 'parent_id', type: 'integer', nullable: true, example: null),
        new OA\Property(property: 'parent', ref: '#/components/schemas/CategorySummary', nullable: true),
        new OA\Property(
            property: 'children',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/CategorySummary')
        ),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
    type: 'object'
)]
class CategoryController extends Controller
{
    #[OA\Get(
        path: '/api/categories',
        operationId: 'listCategories',
        summary: 'Danh sách danh mục',
        description: 'Lấy danh sách danh mục kèm các danh mục con. Truyền per_page=0 để lấy toàn bộ.',
        tags: ['Danh mục'],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 0, maximum: 100, default: 20), example: 20),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, default: 1), example: 1),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lấy danh sách danh mục thành công',
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
                                    items: new OA\Items(ref: '#/components/schemas/CategoryDetail')
                                ),
                                new OA\Property(
                                    property: 'pagination',
                                    properties: [
                                        new OA\Property(property: 'page', type: 'integer', example: 1),
                                        new OA\Property(property: 'limit', type: 'integer', example: 20),
                                        new OA\Property(property: 'totalItems', type: 'integer', example: 15),
                                        new OA\Property(property: 'totalPages', type: 'integer', example: 1),
                                    ],
                                    type: 'object',
                                    nullable: true
                                ),
                            ],
                            type: 'object'
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 422, description: 'Dữ liệu phân trang không hợp lệ'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => 'sometimes|integer|min:0|max:100',
            'page' => 'sometimes|integer|min:1',
        ]);

        $perPage = (int) ($validated['per_page'] ?? 20);
        $page = (int) ($validated['page'] ?? 1);

        $query = Category::with('children');

        if ($perPage <= 0) {
            $items = $query->get()->toArray();
            $payload = [
                'status' => 200,
                'success' => true,
                'message' => null,
                'data' => [
                    'items' => $items,
                    'pagination' => null,
                ],
            ];

            return response()->json($payload, 200);
        }

        $cats = $query->paginate($perPage, ['*'], 'page', $page);

        $payload = [
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => [
                'items' => $cats->items(),
                'pagination' => [
                    'page' => $cats->currentPage(),
                    'limit' => $cats->perPage(),
                    'totalItems' => $cats->total(),
                    'totalPages' => $cats->lastPage(),
                ],
            ],
        ];

        return response()->json($payload, 200);
    }

    #[OA\Get(
        path: '/api/categories/{id}',
        operationId: 'getCategory',
        summary: 'Chi tiết danh mục',
        tags: ['Danh mục'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lấy thông tin danh mục thành công',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 200),
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', nullable: true, example: null),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'items', ref: '#/components/schemas/CategoryDetail'),
                                new OA\Property(property: 'pagination', type: 'object', nullable: true, example: null),
                            ],
                            type: 'object'
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 404, description: 'Không tìm thấy danh mục'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $cat = Category::with(['children', 'parent'])->findOrFail($id);

        $payload = [
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => [
                'items' => $cat->toArray(),
                'pagination' => null,
            ],
        ];

        return response()->json($payload, 200);
    }

    #[OA\Post(
        path: '/api/categories',
        operationId: 'createCategory',
        summary: 'Tạo danh mục',
        security: [['bearerAuth' => []]],
        tags: ['Danh mục'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Áo thun'),
                    new OA\Property(property: 'parent_id', type: 'integer', nullable: true, example: null),
                    new OA\Property(
                        property: 'image',
                        description: 'URL ảnh danh mục. Nếu không gửi hoặc gửi null/rỗng, database sử dụng ảnh mặc định.',
                        type: 'string',
                        format: 'uri',
                        nullable: true,
                        example: 'https://cdn.example.com/categories/ao-thun.jpg'
                    ),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Tạo danh mục thành công',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 201),
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', nullable: true, example: null),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'items', ref: '#/components/schemas/CategorySummary'),
                                new OA\Property(property: 'pagination', type: 'object', nullable: true, example: null),
                            ],
                            type: 'object'
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Chưa xác thực'),
            new OA\Response(response: 403, description: 'Chỉ admin được phép thao tác'),
            new OA\Response(response: 422, description: 'Dữ liệu không hợp lệ'),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|integer|exists:categories,id',
            'image' => 'nullable|url|max:2048',
        ]);

        $cat = new Category;
        $cat->name = $validated['name'];
        $cat->parent_id = $validated['parent_id'] ?? null;

        if ($request->filled('image')) {
            $cat->image = $validated['image'];
        }

        $cat->save();
        $cat->refresh()->load(['children', 'parent']);

        $payload = [
            'status' => 201,
            'success' => true,
            'message' => null,
            'data' => ['items' => $cat->toArray(), 'pagination' => null],
        ];

        return response()->json($payload, 201);
    }

    #[OA\Put(
        path: '/api/categories/{id}',
        operationId: 'updateCategory',
        summary: 'Cập nhật danh mục',
        security: [['bearerAuth' => []]],
        tags: ['Danh mục'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Áo sơ mi'),
                    new OA\Property(property: 'parent_id', type: 'integer', nullable: true, example: null),
                    new OA\Property(
                        property: 'image',
                        description: 'URL ảnh mới. Nếu không gửi hoặc gửi null/rỗng, ảnh hiện tại được giữ nguyên.',
                        type: 'string',
                        format: 'uri',
                        nullable: true,
                        example: 'https://cdn.example.com/categories/ao-so-mi.jpg'
                    ),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Cập nhật danh mục thành công',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 200),
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', nullable: true, example: null),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'items', ref: '#/components/schemas/CategoryDetail'),
                                new OA\Property(property: 'pagination', type: 'object', nullable: true, example: null),
                            ],
                            type: 'object'
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Chưa xác thực'),
            new OA\Response(response: 403, description: 'Chỉ admin được phép thao tác'),
            new OA\Response(response: 404, description: 'Không tìm thấy danh mục'),
            new OA\Response(response: 422, description: 'Dữ liệu không hợp lệ'),
        ]
    )]
    public function update(Request $request, int $id): JsonResponse
    {
        $cat = Category::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'parent_id' => 'nullable|integer|exists:categories,id',
            'image' => 'nullable|url|max:2048',
        ]);

        if (array_key_exists('parent_id', $validated) && $validated['parent_id'] !== null) {
            $this->ensureParentDoesNotCreateCycle($cat, (int) $validated['parent_id']);
        }

        if (array_key_exists('name', $validated)) {
            $cat->name = $validated['name'];
        }
        if (array_key_exists('parent_id', $validated)) {
            $cat->parent_id = $validated['parent_id'];
        }
        if ($request->filled('image')) {
            $cat->image = $validated['image'];
        }

        $cat->save();
        $cat->load(['children', 'parent']);

        $payload = [
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => ['items' => $cat->toArray(), 'pagination' => null],
        ];

        return response()->json($payload, 200);
    }

    #[OA\Delete(
        path: '/api/categories/{id}',
        operationId: 'deleteCategory',
        summary: 'Xóa danh mục',
        security: [['bearerAuth' => []]],
        tags: ['Danh mục'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Xóa danh mục thành công',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 200),
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', nullable: true, example: null),
                        new OA\Property(property: 'data', type: 'object', nullable: true, example: null),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Chưa xác thực'),
            new OA\Response(response: 403, description: 'Chỉ admin được phép thao tác'),
            new OA\Response(response: 404, description: 'Không tìm thấy danh mục'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $cat = Category::findOrFail($id);
        $cat->delete();

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => null,
        ], 200);
    }

    private function ensureParentDoesNotCreateCycle(Category $category, int $parentId): void
    {
        $parent = Category::find($parentId);

        while ($parent) {
            if ($parent->id === $category->id) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Danh mục cha không được là chính danh mục hoặc danh mục con của nó.',
                ]);
            }

            $parent = $parent->parent;
        }
    }
}
