<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttributeType;
use App\Models\AttributeValue;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AttributeValue',
    required: ['id', 'attribute_type_id', 'value', 'display_value'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'attribute_type_id', type: 'integer', example: 1),
        new OA\Property(property: 'value', type: 'string', example: 'black'),
        new OA\Property(property: 'display_value', type: 'string', example: 'Đen'),
        new OA\Property(property: 'meta_data', type: 'object', nullable: true, example: ['hex' => '#000000']),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
    type: 'object'
)]
class AttributeValueController extends Controller
{
    #[OA\Get(
        path: '/api/attributes/{attributeType}/values',
        operationId: 'listAttributeValues',
        summary: 'Danh sách giá trị thuộc tính',
        description: 'Có thể truyền ID hoặc name của loại thuộc tính.',
        tags: ['Thuộc tính'],
        parameters: [
            new OA\Parameter(name: 'attributeType', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'color'),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lấy danh sách giá trị thuộc tính thành công',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 200),
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', nullable: true, example: null),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/AttributeValue')
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 404, description: 'Không tìm thấy loại thuộc tính'),
        ]
    )]
    public function index(string $attributeType): JsonResponse
    {
        $type = $this->resolveAttributeType($attributeType);

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => $type->attributeValues()->orderBy('id')->get(),
        ]);
    }

    #[OA\Get(
        path: '/api/attributes/{attributeType}/values/{attributeValue}',
        operationId: 'getAttributeValue',
        summary: 'Chi tiết giá trị thuộc tính',
        tags: ['Thuộc tính'],
        parameters: [
            new OA\Parameter(name: 'attributeType', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'color'),
            new OA\Parameter(name: 'attributeValue', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lấy giá trị thuộc tính thành công',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 200),
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', nullable: true, example: null),
                        new OA\Property(property: 'data', ref: '#/components/schemas/AttributeValue'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 404, description: 'Không tìm thấy loại hoặc giá trị thuộc tính'),
        ]
    )]
    public function show(string $attributeType, int $attributeValue): JsonResponse
    {
        $value = $this->resolveAttributeValue(
            $this->resolveAttributeType($attributeType),
            $attributeValue
        );

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => $value,
        ]);
    }

    #[OA\Post(
        path: '/api/attributes/{attributeType}/values',
        operationId: 'createAttributeValue',
        summary: 'Tạo giá trị thuộc tính',
        security: [['bearerAuth' => []]],
        tags: ['Thuộc tính'],
        parameters: [
            new OA\Parameter(name: 'attributeType', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['value'],
                properties: [
                    new OA\Property(property: 'value', type: 'string', example: 'black'),
                    new OA\Property(property: 'display_value', description: 'Mặc định bằng value nếu không gửi.', type: 'string', nullable: true, example: 'Đen'),
                    new OA\Property(property: 'meta_data', type: 'object', nullable: true, example: ['hex' => '#000000']),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Tạo giá trị thuộc tính thành công',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 201),
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', nullable: true, example: null),
                        new OA\Property(property: 'data', ref: '#/components/schemas/AttributeValue'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Chưa xác thực'),
            new OA\Response(response: 403, description: 'Chỉ quản trị viên được phép'),
            new OA\Response(response: 404, description: 'Không tìm thấy loại thuộc tính'),
            new OA\Response(response: 422, description: 'Dữ liệu không hợp lệ hoặc value đã tồn tại'),
        ]
    )]
    public function store(Request $request, int $attributeType): JsonResponse
    {
        $type = AttributeType::findOrFail($attributeType);

        $validated = $request->validate([
            'value' => [
                'required',
                'string',
                'max:255',
                Rule::unique('attribute_values', 'value')
                    ->where('attribute_type_id', $type->id),
            ],
            'display_value' => 'nullable|string|max:255',
            'meta_data' => 'nullable|array',
        ]);

        $value = $type->attributeValues()->create([
            'value' => $validated['value'],
            'display_value' => $validated['display_value'] ?? $validated['value'],
            'meta_data' => $validated['meta_data'] ?? null,
        ]);

        return response()->json([
            'status' => 201,
            'success' => true,
            'message' => null,
            'data' => $value,
        ], 201);
    }

    #[OA\Put(
        path: '/api/attributes/{attributeType}/values/{attributeValue}',
        operationId: 'updateAttributeValue',
        summary: 'Cập nhật giá trị thuộc tính',
        security: [['bearerAuth' => []]],
        tags: ['Thuộc tính'],
        parameters: [
            new OA\Parameter(name: 'attributeType', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1),
            new OA\Parameter(name: 'attributeValue', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'value', type: 'string', example: 'black'),
                    new OA\Property(property: 'display_value', type: 'string', example: 'Màu đen'),
                    new OA\Property(property: 'meta_data', type: 'object', nullable: true, example: ['hex' => '#000000']),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Cập nhật giá trị thuộc tính thành công',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 200),
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', nullable: true, example: null),
                        new OA\Property(property: 'data', ref: '#/components/schemas/AttributeValue'),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Chưa xác thực'),
            new OA\Response(response: 403, description: 'Chỉ quản trị viên được phép'),
            new OA\Response(response: 404, description: 'Không tìm thấy loại hoặc giá trị thuộc tính'),
            new OA\Response(response: 422, description: 'Dữ liệu không hợp lệ hoặc value đã tồn tại'),
        ]
    )]
    public function update(Request $request, int $attributeType, int $attributeValue): JsonResponse
    {
        $type = AttributeType::findOrFail($attributeType);
        $value = $this->resolveAttributeValue($type, $attributeValue);

        $validated = $request->validate([
            'value' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('attribute_values', 'value')
                    ->where('attribute_type_id', $type->id)
                    ->ignore($value->id),
            ],
            'display_value' => 'sometimes|required|string|max:255',
            'meta_data' => 'sometimes|nullable|array',
        ]);

        $value->update($validated);

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => $value->fresh(),
        ]);
    }

    #[OA\Delete(
        path: '/api/attributes/{attributeType}/values/{attributeValue}',
        operationId: 'deleteAttributeValue',
        summary: 'Xóa giá trị thuộc tính',
        description: 'Không cho phép xóa nếu giá trị đang được gắn với bất kỳ biến thể sản phẩm nào.',
        security: [['bearerAuth' => []]],
        tags: ['Thuộc tính'],
        parameters: [
            new OA\Parameter(name: 'attributeType', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1),
            new OA\Parameter(name: 'attributeValue', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Xóa giá trị thuộc tính thành công',
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
            new OA\Response(response: 404, description: 'Không tìm thấy loại hoặc giá trị thuộc tính'),
            new OA\Response(
                response: 409,
                description: 'Giá trị thuộc tính đang được sản phẩm sử dụng',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 409),
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Không thể xóa vì giá trị thuộc tính đang được sử dụng.'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(
                                    property: 'usages',
                                    type: 'array',
                                    items: new OA\Items(
                                        properties: [
                                            new OA\Property(property: 'product_id', type: 'integer', example: 10),
                                            new OA\Property(property: 'product_name', type: 'string', nullable: true, example: 'Áo thun'),
                                            new OA\Property(property: 'product_variant_id', type: 'integer', example: 21),
                                            new OA\Property(property: 'sku', type: 'string', example: 'AO-DEN-M'),
                                        ],
                                        type: 'object'
                                    )
                                ),
                            ],
                            type: 'object'
                        ),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function destroy(int $attributeType, int $attributeValue): JsonResponse
    {
        $type = AttributeType::findOrFail($attributeType);
        $value = $this->resolveAttributeValue($type, $attributeValue);
        $usages = $this->valueUsages($value);

        if ($usages->isNotEmpty()) {
            return response()->json([
                'status' => 409,
                'success' => false,
                'message' => 'Không thể xóa vì giá trị thuộc tính đang được sử dụng.',
                'data' => ['usages' => $usages],
            ], 409);
        }

        $value->delete();

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => null,
        ]);
    }

    private function resolveAttributeType(string $identifier): AttributeType
    {
        $type = ctype_digit($identifier)
            ? AttributeType::find((int) $identifier)
            : null;

        return $type ?? AttributeType::where('name', $identifier)->firstOrFail();
    }

    private function resolveAttributeValue(AttributeType $type, int $attributeValue): AttributeValue
    {
        return $type->attributeValues()->findOrFail($attributeValue);
    }

    private function valueUsages(AttributeValue $value)
    {
        return $value->productVariants()
            ->with('product:id,name')
            ->get()
            ->map(fn ($variant) => [
                'product_id' => $variant->product_id,
                'product_name' => $variant->product?->name,
                'product_variant_id' => $variant->id,
                'sku' => $variant->sku,
            ])
            ->values();
    }
}
