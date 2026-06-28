<?php

namespace App\Http\Controllers;

use App\Http\Services\VoucherService;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class VoucherController extends Controller
{
    private VoucherService $voucherService;

    public function __construct(VoucherService $voucherService)
    {
        $this->voucherService = $voucherService;
    }

    #[OA\Get(
        path: '/api/voucher',
        operationId: 'listVouchers',
        summary: 'Danh sách voucher',
        description: 'Lấy danh sách voucher dành cho quản trị viên, bao gồm cả voucher đã bị tắt.',
        security: [['bearerAuth' => []]],
        tags: ['Voucher'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, default: 1), example: 1),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20), example: 20),
            new OA\Parameter(name: 'q', in: 'query', description: 'Tìm theo mã hoặc mô tả.', required: false, schema: new OA\Schema(type: 'string'), example: 'WELCOME'),
            new OA\Parameter(name: 'is_active', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'), example: true),
            new OA\Parameter(name: 'discount_type', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['ORDER', 'SHIPPING'])),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['valid', 'expired'])),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lấy danh sách voucher thành công',
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
                                            new OA\Property(property: 'code', type: 'string', example: 'WELCOME10'),
                                            new OA\Property(property: 'description', type: 'string', nullable: true),
                                            new OA\Property(property: 'discount_amount', type: 'number', format: 'float', example: 10),
                                            new OA\Property(property: 'max_discount_amount', type: 'number', format: 'float', nullable: true, example: 50000),
                                            new OA\Property(property: 'discount_type', type: 'string', enum: ['ORDER', 'SHIPPING']),
                                            new OA\Property(property: 'is_active', type: 'boolean', example: true),
                                            new OA\Property(property: 'usage_limit', type: 'integer', example: 100),
                                            new OA\Property(property: 'expiry_date', type: 'string', format: 'date'),
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
            new OA\Response(response: 401, description: 'Chưa xác thực'),
            new OA\Response(response: 403, description: 'Chỉ quản trị viên được phép'),
            new OA\Response(response: 422, description: 'Tham số lọc không hợp lệ'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $this->ensureAdmin($request->user());
        $this->normalizeBooleanInput($request, 'is_active');

        $filters = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'q' => 'sometimes|nullable|string|max:255',
            'is_active' => 'sometimes|boolean',
            'discount_type' => 'sometimes|in:ORDER,SHIPPING',
            'status' => 'sometimes|in:valid,expired',
        ]);

        $vouchers = $this->voucherService->paginate($filters);

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => [
                'items' => $vouchers->items(),
                'pagination' => [
                    'page' => $vouchers->currentPage(),
                    'limit' => $vouchers->perPage(),
                    'totalItems' => $vouchers->total(),
                    'totalPages' => $vouchers->lastPage(),
                ],
            ],
        ]);
    }

    #[OA\Post(
        path: '/api/voucher',
        operationId: 'createVoucher',
        summary: 'Tạo voucher',
        description: 'Nếu code rỗng hoặc null, hệ thống tự sinh code 10 ký tự chữ và số không trùng.',
        security: [['bearerAuth' => []]],
        tags: ['Voucher'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['discount_amount', 'discount_type', 'usage_limit', 'expiry_date'],
                properties: [
                    new OA\Property(property: 'code', description: 'Không bắt buộc; tự sinh nếu bỏ trống.', type: 'string', maxLength: 50, nullable: true, example: 'WELCOME10'),
                    new OA\Property(property: 'description', type: 'string', maxLength: 255, nullable: true, example: 'Giảm 10% cho đơn hàng'),
                    new OA\Property(property: 'discount_amount', description: 'Phần trăm giảm từ 0 đến 100.', type: 'number', format: 'float', exclusiveMinimum: 0, maximum: 100, example: 10),
                    new OA\Property(property: 'max_discount_amount', type: 'number', format: 'float', minimum: 0, nullable: true, example: 50000),
                    new OA\Property(property: 'discount_type', type: 'string', enum: ['ORDER', 'SHIPPING'], example: 'ORDER'),
                    new OA\Property(property: 'is_active', type: 'boolean', default: true, example: true),
                    new OA\Property(property: 'usage_limit', type: 'integer', minimum: 1, example: 100),
                    new OA\Property(property: 'expiry_date', type: 'string', format: 'date', example: '2026-12-31'),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Tạo voucher thành công'),
            new OA\Response(response: 401, description: 'Chưa xác thực'),
            new OA\Response(response: 403, description: 'Chỉ quản trị viên được phép'),
            new OA\Response(response: 422, description: 'Dữ liệu không hợp lệ hoặc code đã tồn tại'),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $this->ensureAdmin($request->user());
        $this->normalizeCodeInput($request);

        $data = $request->validate([
            'code' => 'nullable|string|alpha_num|max:50|unique:vouchers,code',
            'description' => 'nullable|string|max:255',
            'discount_amount' => 'required|numeric|gt:0|lte:100',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'discount_type' => 'required|in:ORDER,SHIPPING',
            'is_active' => 'sometimes|boolean',
            'usage_limit' => 'required|integer|min:1',
            'expiry_date' => 'required|date|after_or_equal:today',
        ]);

        return response()->json([
            'status' => 201,
            'success' => true,
            'message' => null,
            'data' => $this->voucherService->create($data),
        ], 201);
    }

    #[OA\Get(
        path: '/api/voucher/{voucher}',
        operationId: 'getVoucherByCode',
        summary: 'Lấy chi tiết voucher theo mã',
        description: 'Khách hàng chỉ xem được voucher còn hiệu lực theo code. Quản trị viên có thể xem voucher theo code hoặc ID.',
        security: [['bearerAuth' => []]],
        tags: ['Voucher'],
        parameters: [
            new OA\Parameter(
                name: 'voucher',
                description: 'Code voucher; quản trị viên cũng có thể truyền ID.',
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
                                new OA\Property(property: 'description', type: 'string', nullable: true, example: 'Giảm 10% cho đơn hàng'),
                                new OA\Property(property: 'discount_amount', type: 'number', format: 'float', example: 10),
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
            new OA\Response(response: 404, description: 'Quản trị viên không tìm thấy voucher'),
        ]
    )]
    public function show(Request $request, string $voucher): JsonResponse
    {
        $result = $request->user()?->role === 'ROLE_ADMIN'
            ? $this->resolveVoucher($voucher)
            : $this->voucherService->getVoucherByCode($voucher);

        if (! $result) {
            throw ValidationException::withMessages([
                'voucher' => 'Voucher không hợp lệ hoặc đã hết hạn.',
            ]);
        }

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => $result,
        ]);
    }

    #[OA\Put(
        path: '/api/voucher/{voucher}',
        operationId: 'updateVoucher',
        summary: 'Cập nhật voucher',
        description: 'Cập nhật voucher theo code hoặc ID. Chỉ cần gửi các trường muốn thay đổi.',
        security: [['bearerAuth' => []]],
        tags: ['Voucher'],
        parameters: [
            new OA\Parameter(name: 'voucher', in: 'path', description: 'Code hoặc ID voucher.', required: true, schema: new OA\Schema(type: 'string'), example: 'WELCOME10'),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'code', type: 'string', maxLength: 50, example: 'SUMMER2026'),
                    new OA\Property(property: 'description', type: 'string', maxLength: 255, nullable: true, example: 'Giảm 15% cho đơn hàng'),
                    new OA\Property(property: 'discount_amount', type: 'number', format: 'float', exclusiveMinimum: 0, maximum: 100, example: 15),
                    new OA\Property(property: 'max_discount_amount', type: 'number', format: 'float', minimum: 0, nullable: true, example: 75000),
                    new OA\Property(property: 'discount_type', type: 'string', enum: ['ORDER', 'SHIPPING'], example: 'ORDER'),
                    new OA\Property(property: 'is_active', type: 'boolean', example: true),
                    new OA\Property(property: 'usage_limit', type: 'integer', minimum: 0, example: 50),
                    new OA\Property(property: 'expiry_date', type: 'string', format: 'date', example: '2027-01-31'),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Cập nhật voucher thành công'),
            new OA\Response(response: 401, description: 'Chưa xác thực'),
            new OA\Response(response: 403, description: 'Chỉ quản trị viên được phép'),
            new OA\Response(response: 404, description: 'Không tìm thấy voucher'),
            new OA\Response(response: 422, description: 'Dữ liệu không hợp lệ hoặc code đã tồn tại'),
        ]
    )]
    public function update(Request $request, string $voucher): JsonResponse
    {
        $this->ensureAdmin($request->user());
        $model = $this->resolveVoucher($voucher);

        if ($request->has('code')) {
            $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        }
        $this->normalizeBooleanInput($request, 'is_active');

        $data = $request->validate([
            'code' => [
                'sometimes',
                'required',
                'string',
                'alpha_num',
                'max:50',
                Rule::unique('vouchers', 'code')->ignore($model->id),
            ],
            'description' => 'sometimes|nullable|string|max:255',
            'discount_amount' => 'sometimes|required|numeric|gt:0|lte:100',
            'max_discount_amount' => 'sometimes|nullable|numeric|min:0',
            'discount_type' => 'sometimes|required|in:ORDER,SHIPPING',
            'is_active' => 'sometimes|boolean',
            'usage_limit' => 'sometimes|required|integer|min:0',
            'expiry_date' => 'sometimes|required|date|after_or_equal:today',
        ]);

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => $this->voucherService->update($model, $data),
        ]);
    }

    #[OA\Delete(
        path: '/api/voucher/{voucher}',
        operationId: 'deleteVoucher',
        summary: 'Tắt voucher',
        description: 'Không xóa bản ghi; hệ thống cập nhật is_active thành false để voucher không thể tiếp tục sử dụng.',
        security: [['bearerAuth' => []]],
        tags: ['Voucher'],
        parameters: [
            new OA\Parameter(name: 'voucher', in: 'path', description: 'Code hoặc ID voucher.', required: true, schema: new OA\Schema(type: 'string'), example: 'WELCOME10'),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tắt voucher thành công',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 200),
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', nullable: true, example: null),
                        new OA\Property(property: 'data', type: 'boolean', example: true),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Chưa xác thực'),
            new OA\Response(response: 403, description: 'Chỉ quản trị viên được phép'),
            new OA\Response(response: 404, description: 'Không tìm thấy voucher'),
        ]
    )]
    public function destroy(Request $request, string $voucher): JsonResponse
    {
        $this->ensureAdmin($request->user());
        $this->voucherService->delete($this->resolveVoucher($voucher));

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => true,
        ]);
    }

    private function ensureAdmin(?User $user): void
    {
        if (! $user || $user->role !== 'ROLE_ADMIN') {
            abort(403, 'Chỉ quản trị viên được phép');
        }
    }

    private function resolveVoucher(string $identifier): Voucher
    {
        $voucher = Voucher::where('code', strtoupper($identifier))->first();

        if (! $voucher && ctype_digit($identifier)) {
            $voucher = Voucher::find((int) $identifier);
        }

        return $voucher ?? abort(404, 'Không tìm thấy voucher');
    }

    private function normalizeCodeInput(Request $request): void
    {
        $code = strtoupper(trim((string) $request->input('code', '')));
        $request->merge(['code' => $code !== '' ? $code : null]);
    }

    private function normalizeBooleanInput(Request $request, string $field): void
    {
        if (! $request->has($field)) {
            return;
        }

        $value = $request->input($field);

        if (in_array($value, ['true', '1', 1, true], true)) {
            $request->merge([$field => true]);
        } elseif (in_array($value, ['false', '0', 0, false], true)) {
            $request->merge([$field => false]);
        }
    }
}
