<?php

namespace App\Http\Controllers;

use App\Http\Services\AddressService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class AddressController extends Controller
{
    private AddressService $addressService;

    public function __construct(AddressService $addressService)
    {
        $this->addressService = $addressService;
    }

    #[OA\Get(
        path: '/api/addresses',
        operationId: 'getUserAddresses',
        summary: 'Lấy danh sách địa chỉ của người dùng',
        security: [['bearerAuth' => []]],
        tags: ['Địa chỉ'],
        responses: [
            new OA\Response(response: 200, description: 'Lấy danh sách địa chỉ thành công'),
            new OA\Response(response: 401, description: 'Chưa xác thực'),
        ]
    )]
    public function index(Request $request)
    {
        return $this->success($this->addressService->getAddresses($request->user())->toArray());
    }

    #[OA\Post(
        path: '/api/addresses',
        operationId: 'createUserAddress',
        summary: 'Thêm địa chỉ mới',
        security: [['bearerAuth' => []]],
        tags: ['Địa chỉ'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['district_id', 'district_name', 'ward_code', 'ward_name', 'province_id', 'province_name', 'specific_address', 'full_name', 'phone'],
                properties: [
                    new OA\Property(property: 'address_code', type: 'string', nullable: true, example: 'ADDR-HOME'),
                    new OA\Property(property: 'district_id', type: 'integer', example: 3695),
                    new OA\Property(property: 'district_name', type: 'string', example: 'Thành Phố Thủ Đức'),
                    new OA\Property(property: 'ward_code', type: 'string', example: '90768'),
                    new OA\Property(property: 'ward_name', type: 'string', example: 'Phường An Khánh'),
                    new OA\Property(property: 'province_id', type: 'integer', example: 202),
                    new OA\Property(property: 'province_name', type: 'string', example: 'Hồ Chí Minh'),
                    new OA\Property(property: 'specific_address', type: 'string', example: '12 Nguyễn Văn A'),
                    new OA\Property(property: 'full_name', type: 'string', example: 'Nguyễn Văn A'),
                    new OA\Property(property: 'phone', type: 'string', example: '0901234567'),
                    new OA\Property(property: 'is_default', type: 'boolean', example: true),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Thêm địa chỉ thành công'),
            new OA\Response(response: 422, description: 'Dữ liệu không hợp lệ'),
        ]
    )]
    public function store(Request $request)
    {
        $address = $this->addressService->create($request->user(), $this->validatedData($request));

        return $this->success($address->toArray(), 201);
    }

    #[OA\Put(
        path: '/api/addresses/{address}',
        operationId: 'updateUserAddress',
        summary: 'Cập nhật địa chỉ',
        security: [['bearerAuth' => []]],
        tags: ['Địa chỉ'],
        parameters: [
            new OA\Parameter(name: 'address', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Cập nhật địa chỉ thành công'),
            new OA\Response(response: 404, description: 'Không tìm thấy địa chỉ'),
            new OA\Response(response: 422, description: 'Dữ liệu không hợp lệ'),
        ]
    )]
    public function update(Request $request, int $address)
    {
        $updatedAddress = $this->addressService->update($request->user(), $address, $this->validatedData($request, true, $address));

        return $this->success($updatedAddress->toArray());
    }

    #[OA\Delete(
        path: '/api/addresses/{address}',
        operationId: 'deleteUserAddress',
        summary: 'Xóa địa chỉ',
        security: [['bearerAuth' => []]],
        tags: ['Địa chỉ'],
        parameters: [
            new OA\Parameter(name: 'address', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Xóa địa chỉ thành công'),
            new OA\Response(response: 404, description: 'Không tìm thấy địa chỉ'),
        ]
    )]
    public function destroy(Request $request, int $address)
    {
        $this->addressService->delete($request->user(), $address);

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => null,
        ], 200);
    }

    #[OA\Put(
        path: '/api/addresses/{address}/default',
        operationId: 'setDefaultUserAddress',
        summary: 'Đặt địa chỉ làm mặc định',
        security: [['bearerAuth' => []]],
        tags: ['Địa chỉ'],
        parameters: [
            new OA\Parameter(name: 'address', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 1),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Đặt địa chỉ mặc định thành công'),
            new OA\Response(response: 404, description: 'Không tìm thấy địa chỉ'),
        ]
    )]
    public function setDefault(Request $request, int $address)
    {
        $defaultAddress = $this->addressService->setDefault($request->user(), $address);

        return $this->success($defaultAddress->toArray());
    }

    private function validatedData(Request $request, bool $isUpdate = false, ?int $addressId = null): array
    {
        $required = $isUpdate ? 'sometimes|required' : 'required';

        return $request->validate([
            'address_code' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('addresses', 'address_code')->ignore($addressId),
            ],
            'district_id' => "{$required}|integer",
            'district_name' => "{$required}|string|max:255",
            'ward_code' => "{$required}|string|max:50",
            'ward_name' => "{$required}|string|max:255",
            'province_id' => "{$required}|integer",
            'province_name' => "{$required}|string|max:255",
            'specific_address' => "{$required}|string|max:255",
            'full_name' => "{$required}|string|max:255",
            'phone' => "{$required}|string|max:20",
            'is_default' => 'nullable|boolean',
        ]);
    }

    private function success($items, int $status = 200)
    {
        return response()->json([
            'status' => $status,
            'success' => true,
            'message' => null,
            'data' => [
                'items' => $items,
                'pagination' => null,
            ],
        ], $status);
    }
}
