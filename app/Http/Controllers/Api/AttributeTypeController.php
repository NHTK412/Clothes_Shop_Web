<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAttributeTypeRequest;
use App\Http\Requests\UpdateAttributeTypeRequest;
use App\Models\AttributeType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class AttributeTypeController extends Controller
{
    #[OA\Get(
        path: '/api/attributes',
        summary: 'Danh sách loại thuộc tính',
        tags: ['Thuộc tính'],
        responses: [
            new OA\Response(response: 200, description: 'Lấy danh sách loại thuộc tính thành công'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $types = AttributeType::with('attributeValues')->get();

        return response()->json($types);
    }

    #[OA\Get(
        path: '/api/attributes/{id}',
        summary: 'Chi tiết loại thuộc tính',
        tags: ['Thuộc tính'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lấy thông tin loại thuộc tính thành công'),
            new OA\Response(response: 404, description: 'Không tìm thấy loại thuộc tính'),
        ]
    )]
    public function show($id): JsonResponse
    {
        $type = AttributeType::with('attributeValues')->findOrFail($id);

        return response()->json($type);
    }

    #[OA\Post(
        path: '/api/attributes',
        summary: 'Tạo loại thuộc tính',
        security: [['bearerAuth' => []]],
        tags: ['Thuộc tính'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'màu sắc'),
                    new OA\Property(
                        property: 'values',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        example: ['đen', 'trắng', 'xanh']
                    ),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Tạo loại thuộc tính thành công'),
            new OA\Response(response: 422, description: 'Dữ liệu không hợp lệ'),
        ]
    )]
    public function store(StoreAttributeTypeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $type = AttributeType::create(['name' => $data['name']]);

        if (! empty($data['values']) && is_array($data['values'])) {
            foreach ($data['values'] as $value) {
                $type->attributeValues()->create(['value' => $value]);
            }
        }

        return response()->json($type->load('attributeValues'), 201);
    }

    #[OA\Put(
        path: '/api/attributes/{id}',
        summary: 'Cập nhật loại thuộc tính',
        security: [['bearerAuth' => []]],
        tags: ['Thuộc tính'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'kích cỡ'),
                    new OA\Property(
                        property: 'values',
                        type: 'array',
                        items: new OA\Items(type: 'string'),
                        example: ['S', 'M', 'L']
                    ),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Cập nhật loại thuộc tính thành công'),
            new OA\Response(response: 404, description: 'Không tìm thấy loại thuộc tính'),
            new OA\Response(response: 422, description: 'Dữ liệu không hợp lệ'),
        ]
    )]
    public function update(UpdateAttributeTypeRequest $request, $id): JsonResponse
    {
        $type = AttributeType::findOrFail($id);
        $data = $request->validated();
        $type->update(['name' => $data['name']]);

        if (array_key_exists('values', $data)) {
            // replace existing values with provided list
            $type->attributeValues()->delete();
            if (! empty($data['values']) && is_array($data['values'])) {
                foreach ($data['values'] as $value) {
                    $type->attributeValues()->create(['value' => $value]);
                }
            }
        }

        return response()->json($type->load('attributeValues'));
    }

    #[OA\Delete(
        path: '/api/attributes/{id}',
        summary: 'Xóa loại thuộc tính',
        security: [['bearerAuth' => []]],
        tags: ['Thuộc tính'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Xóa loại thuộc tính thành công'),
            new OA\Response(response: 404, description: 'Không tìm thấy loại thuộc tính'),
        ]
    )]
    public function destroy($id): JsonResponse
    {
        $type = AttributeType::findOrFail($id);
        $type->attributeValues()->delete();
        $type->delete();

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => null,
        ], 200);
    }

    /**
     * Trả về danh sách giá trị theo id hoặc tên loại thuộc tính.
     */
    #[OA\Get(
        path: '/api/attributes/{idOrName}/values',
        summary: 'Danh sách giá trị của thuộc tính',
        tags: ['Thuộc tính'],
        parameters: [
            new OA\Parameter(name: 'idOrName', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'màu sắc'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lấy danh sách giá trị thuộc tính thành công'),
            new OA\Response(response: 404, description: 'Không tìm thấy loại thuộc tính'),
        ]
    )]
    public function values(Request $request, $idOrName): JsonResponse
    {
        // allow passing numeric id or attribute name
        $type = null;
        if (is_numeric($idOrName)) {
            $type = AttributeType::with('attributeValues')->find($idOrName);
        }
        if (! $type) {
            $type = AttributeType::with('attributeValues')->where('name', $idOrName)->first();
        }
        if (! $type) {
            return response()->json([
                'status' => 404,
                'success' => false,
                'message' => 'Not found',
                'data' => null,
            ], 404);
        }

        return response()->json(['data' => $type->attributeValues->map(function ($v) {
            return ['id' => $v->id, 'value' => $v->value];
        })->values()]);
    }
}
