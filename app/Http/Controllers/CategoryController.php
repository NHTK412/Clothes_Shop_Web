<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CategoryController extends Controller
{
    #[OA\Get(
        path: '/api/categories',
        summary: 'Danh sách danh mục',
        tags: ['Danh mục'],
        parameters: [
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer'), example: 20),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lấy danh sách danh mục thành công'),
        ]
    )]
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 20);
        $page = (int) $request->query('page', 1);

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
        summary: 'Chi tiết danh mục',
        tags: ['Danh mục'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lấy thông tin danh mục thành công'),
            new OA\Response(response: 404, description: 'Không tìm thấy danh mục'),
        ]
    )]
    public function show($id)
    {
        $cat = Category::with(['children','parent'])->findOrFail($id);

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
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Tạo danh mục thành công'),
            new OA\Response(response: 403, description: 'Chỉ admin được phép thao tác'),
            new OA\Response(response: 422, description: 'Dữ liệu không hợp lệ'),
        ]
    )]
    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user || (($user->role ?? null) !== 'admin')) {
            return response()->json([
                'status' => 403,
                'success' => false,
                'message' => 'Forbidden: admin only',
                'data' => null,
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|integer|exists:categories,id',
        ]);

        $cat = new Category();
        $cat->name = $validated['name'];
        $cat->parent_id = $validated['parent_id'] ?? null;
        $cat->save();

        $payload = [
            'status' => 201,
            'success' => true,
            'message' => null,
            'data' => [ 'items' => $cat->toArray(), 'pagination' => null ],
        ];

        return response()->json($payload, 201);
    }

    #[OA\Put(
        path: '/api/categories/{id}',
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
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Cập nhật danh mục thành công'),
            new OA\Response(response: 403, description: 'Chỉ admin được phép thao tác'),
            new OA\Response(response: 404, description: 'Không tìm thấy danh mục'),
            new OA\Response(response: 422, description: 'Dữ liệu không hợp lệ'),
        ]
    )]
    public function update(Request $request, $id)
    {
        $user = $request->user();
        if (!$user || (($user->role ?? null) !== 'admin')) {
            return response()->json([
                'status' => 403,
                'success' => false,
                'message' => 'Forbidden: admin only',
                'data' => null,
            ], 403);
        }

        $cat = Category::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'parent_id' => 'nullable|integer|exists:categories,id',
        ]);

        if (array_key_exists('name', $validated)) $cat->name = $validated['name'];
        if (array_key_exists('parent_id', $validated)) $cat->parent_id = $validated['parent_id'];

        $cat->save();

        $payload = [
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => [ 'items' => $cat->toArray(), 'pagination' => null ],
        ];

        return response()->json($payload, 200);
    }

    #[OA\Delete(
        path: '/api/categories/{id}',
        summary: 'Xóa danh mục',
        security: [['bearerAuth' => []]],
        tags: ['Danh mục'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Xóa danh mục thành công'),
            new OA\Response(response: 403, description: 'Chỉ admin được phép thao tác'),
            new OA\Response(response: 404, description: 'Không tìm thấy danh mục'),
        ]
    )]
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        if (!$user || (($user->role ?? null) !== 'admin')) {
            return response()->json([
                'status' => 403,
                'success' => false,
                'message' => 'Forbidden: admin only',
                'data' => null,
            ], 403);
        }

        $cat = Category::findOrFail($id);
        $cat->delete();

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => null,
        ], 200);
    }
}
