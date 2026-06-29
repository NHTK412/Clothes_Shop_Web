<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAttributeTypeRequest;
use App\Http\Requests\UpdateAttributeTypeRequest;
use App\Models\AttributeType;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AttributeType',
    required: ['id', 'name', 'display_name'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'color'),
        new OA\Property(property: 'display_name', type: 'string', example: 'Màu sắc'),
        new OA\Property(
            property: 'attribute_values',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/AttributeValue')
        ),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
    type: 'object'
)]
class AttributeTypeController extends Controller
{
    #[OA\Get(
        path: '/api/attributes',
        operationId: 'listAttributeTypes',
        summary: 'Danh sách loại thuộc tính',
        tags: ['Thuộc tính'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lấy danh sách loại thuộc tính thành công',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/AttributeType')
                )
            ),
        ]
    )]
    public function index(): JsonResponse
    {
        $types = AttributeType::with('attributeValues')->get();

        return response()->json($types);
    }

    #[OA\Get(
        path: '/api/attributes/{id}',
        operationId: 'getAttributeType',
        summary: 'Chi tiết loại thuộc tính',
        tags: ['Thuộc tính'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lấy thông tin loại thuộc tính thành công',
                content: new OA\JsonContent(ref: '#/components/schemas/AttributeType')
            ),
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
        operationId: 'createAttributeType',
        summary: 'Tạo loại thuộc tính',
        security: [['bearerAuth' => []]],
        tags: ['Thuộc tính'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'màu sắc'),
                    new OA\Property(property: 'display_name', description: 'Mặc định bằng name nếu không gửi.', type: 'string', nullable: true, example: 'Màu sắc'),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Tạo loại thuộc tính thành công',
                content: new OA\JsonContent(ref: '#/components/schemas/AttributeType')
            ),
            new OA\Response(response: 401, description: 'Chưa xác thực'),
            new OA\Response(response: 403, description: 'Chỉ quản trị viên được phép'),
            new OA\Response(response: 422, description: 'Dữ liệu không hợp lệ'),
        ]
    )]
    public function store(StoreAttributeTypeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $type = AttributeType::create([
            'name' => $data['name'],
            'display_name' => $data['display_name'] ?? $data['name'],
        ]);

        return response()->json($type->load('attributeValues'), 201);
    }

    #[OA\Put(
        path: '/api/attributes/{id}',
        operationId: 'updateAttributeType',
        summary: 'Cập nhật loại thuộc tính',
        security: [['bearerAuth' => []]],
        tags: ['Thuộc tính'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'kích cỡ'),
                    new OA\Property(property: 'display_name', type: 'string', example: 'Kích cỡ'),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Cập nhật loại thuộc tính thành công',
                content: new OA\JsonContent(ref: '#/components/schemas/AttributeType')
            ),
            new OA\Response(response: 401, description: 'Chưa xác thực'),
            new OA\Response(response: 403, description: 'Chỉ quản trị viên được phép'),
            new OA\Response(response: 404, description: 'Không tìm thấy loại thuộc tính'),
            new OA\Response(response: 422, description: 'Dữ liệu không hợp lệ'),
        ]
    )]
    public function update(UpdateAttributeTypeRequest $request, $id): JsonResponse
    {
        $type = AttributeType::findOrFail($id);
        $data = $request->validated();
        $type->update($data);

        return response()->json($type->load('attributeValues'));
    }

    #[OA\Delete(
        path: '/api/attributes/{id}',
        operationId: 'deleteAttributeType',
        summary: 'Xóa loại thuộc tính',
        description: 'Không cho phép xóa nếu bất kỳ giá trị nào thuộc loại này đang được gắn với biến thể sản phẩm.',
        security: [['bearerAuth' => []]],
        tags: ['Thuộc tính'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Xóa loại thuộc tính thành công',
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
            new OA\Response(response: 403, description: 'Chỉ quản trị viên được phép'),
            new OA\Response(response: 404, description: 'Không tìm thấy loại thuộc tính'),
            new OA\Response(
                response: 409,
                description: 'Có giá trị thuộc tính đang được sản phẩm sử dụng',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 409),
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Không thể xóa vì có giá trị thuộc tính đang được sử dụng.'),
                        new OA\Property(property: 'data', type: 'object'),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function destroy($id): JsonResponse
    {
        $type = AttributeType::with('attributeValues.productVariants.product')->findOrFail($id);
        $usages = $type->attributeValues
            ->flatMap(fn ($value) => $value->productVariants->map(fn ($variant) => [
                'attribute_value_id' => $value->id,
                'attribute_value' => $value->display_value,
                'product_id' => $variant->product_id,
                'product_name' => $variant->product?->name,
                'product_variant_id' => $variant->id,
                'sku' => $variant->sku,
            ]))
            ->values();

        if ($usages->isNotEmpty()) {
            return response()->json([
                'status' => 409,
                'success' => false,
                'message' => 'Không thể xóa vì có giá trị thuộc tính đang được sử dụng.',
                'data' => ['usages' => $usages],
            ], 409);
        }

        $type->delete();

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => null,
        ], 200);
    }
}
