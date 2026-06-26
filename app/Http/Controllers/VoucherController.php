<?php

namespace App\Http\Controllers;

use App\Http\Services\VoucherService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class VoucherController extends Controller
{
    private VoucherService $voucherService;

    public function __construct(VoucherService $voucherService)
    {
        $this->voucherService = $voucherService;
    }

    #[OA\Get(
        path: '/api/voucher/{voucher}',
        operationId: 'getVoucherByCode',
        summary: 'Lấy chi tiết voucher theo mã',
        description: 'Trả về thông tin voucher đang hoạt động và chưa hết hạn theo mã voucher.',
        security: [['bearerAuth' => []]],
        tags: ['Voucher'],
        parameters: [
            new OA\Parameter(
                name: 'voucher',
                description: 'Mã voucher cần kiểm tra.',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string'),
                example: 'SALE100'
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lấy thông tin voucher thành công',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 200),
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', nullable: true, example: null),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'code', type: 'string', example: 'SALE100'),
                                new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Giảm 100.000đ cho đơn hàng'),
                                new OA\Property(property: 'discount_amount', type: 'number', format: 'float', example: 100000),
                                new OA\Property(property: 'max_discount_amount', type: 'number', format: 'float', nullable: true, example: 100000),
                                new OA\Property(property: 'discount_type', type: 'string', enum: ['ORDER', 'SHIPPING'], example: 'ORDER'),
                                new OA\Property(property: 'is_active', type: 'boolean', example: true),
                                new OA\Property(property: 'usage_limit', type: 'integer', example: 100),
                                new OA\Property(property: 'expiry_date', type: 'string', format: 'date', example: '2026-12-31'),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-06-26T13:54:06.000000Z'),
                                new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-06-26T13:54:06.000000Z'),
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
                description: 'Voucher không tồn tại, đã bị tắt hoặc đã hết hạn',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 422),
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'The selected voucher is invalid.'),
                        new OA\Property(property: 'data', type: 'object', nullable: true, example: null),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function show(Request $request, string $voucher)
    {
        validator(['voucher' => $voucher], [
            'voucher' => [
                'required',
                'string',
                Rule::exists('vouchers', 'code')->where(function ($query) {
                    $query->where('is_active', true)
                        ->where('expiry_date', '>=', now());
                }),
            ],
        ])->validate();

        $result = $this->voucherService->getVoucherByCode($voucher);

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => $result,
        ], 200);
    }
}
