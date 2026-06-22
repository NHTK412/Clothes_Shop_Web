<?php

namespace App\Http\Controllers;

use App\Http\Services\OrderService;
use App\Http\Services\VnpayService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class VnpayController extends Controller
{
    public function __construct(
        private OrderService $orderService,
        private VnpayService $vnpayService
    ) {}

    #[OA\Post(
        path: '/api/vnpay/payment-url',
        operationId: 'createVnpayPaymentUrl',
        summary: 'Tạo URL thanh toán VNPAY',
        description: 'Tạo URL thanh toán VNPAY cho một đơn hàng của người dùng đang đăng nhập. Frontend dùng payment_url để chuyển người dùng sang cổng thanh toán VNPAY.',
        security: [['bearerAuth' => []]],
        tags: ['VNPAY'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['order_id'],
                properties: [
                    new OA\Property(property: 'order_id', description: 'ID đơn hàng cần thanh toán.', type: 'integer', example: 1),
                    new OA\Property(property: 'bank_code', description: 'Mã phương thức thanh toán. Không truyền để người dùng tự chọn trên VNPAY.', type: 'string', enum: ['VNPAYQR', 'VNBANK', 'INTCARD'], nullable: true, example: 'VNBANK'),
                    new OA\Property(property: 'locale', description: 'Ngôn ngữ giao diện VNPAY.', type: 'string', enum: ['vn', 'en'], nullable: true, example: 'vn'),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tạo URL thanh toán thành công',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 200),
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', nullable: true, example: null),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'payment_url', type: 'string', example: 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html?vnp_Amount=29800000&vnp_Command=pay&vnp_CreateDate=20260623120000&vnp_CurrCode=VND&vnp_IpAddr=127.0.0.1&vnp_Locale=vn&vnp_OrderInfo=Thanh+toan+don+hang+1&vnp_OrderType=other&vnp_ReturnUrl=https%3A%2F%2Fdomain.vn%2Fvnpay-return&vnp_TmnCode=DEMOV210&vnp_TxnRef=120260623120000&vnp_Version=2.1.0&vnp_SecureHash=...'),
                                new OA\Property(property: 'txn_ref', type: 'string', example: '120260623120000'),
                                new OA\Property(property: 'amount', type: 'number', format: 'float', example: 298000),
                                new OA\Property(property: 'expire_at', type: 'string', example: '20260623121500'),
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
                response: 404,
                description: 'Không tìm thấy đơn hàng hoặc đơn hàng không thuộc người dùng hiện tại',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 404),
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'No query results for model.'),
                        new OA\Property(property: 'data', type: 'object', nullable: true, example: null),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Dữ liệu không hợp lệ hoặc cấu hình VNPAY chưa đầy đủ',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 422),
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'The given data was invalid.'),
                        new OA\Property(property: 'data', type: 'object', nullable: true, example: null),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function createPaymentUrl(Request $request)
    {
        $validated = $request->validate([
            'order_id' => 'required|integer',
            'bank_code' => ['nullable', 'string', Rule::in(['VNPAYQR', 'VNBANK', 'INTCARD'])],
            'locale' => ['nullable', 'string', Rule::in(['vn', 'en'])],
        ]);

        $order = $this->orderService->getOrderById($request->user(), $validated['order_id']);

        $payment = $this->vnpayService->createPaymentUrl(
            $order,
            $request->ip(),
            $validated['bank_code'] ?? null,
            $validated['locale'] ?? null
        );

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => $payment,
        ]);
    }

    #[OA\Get(
        path: '/api/vnpay/return',
        operationId: 'handleVnpayReturn',
        summary: 'Xử lý kết quả thanh toán VNPAY',
        description: 'Endpoint nhận redirect từ VNPAY sau khi người dùng thanh toán. Hệ thống xác thực chữ ký, nếu giao dịch thành công thì cập nhật payment thành PAID và order thành CONFIRMED.',
        tags: ['VNPAY'],
        parameters: [
            new OA\Parameter(name: 'vnp_TxnRef', in: 'query', required: true, schema: new OA\Schema(type: 'string'), example: '120260623120000'),
            new OA\Parameter(name: 'vnp_Amount', in: 'query', required: true, schema: new OA\Schema(type: 'integer'), example: 29800000),
            new OA\Parameter(name: 'vnp_ResponseCode', in: 'query', required: true, schema: new OA\Schema(type: 'string'), example: '00'),
            new OA\Parameter(name: 'vnp_TransactionStatus', in: 'query', required: true, schema: new OA\Schema(type: 'string'), example: '00'),
            new OA\Parameter(name: 'vnp_SecureHash', in: 'query', required: true, schema: new OA\Schema(type: 'string'), example: 'secure_hash_from_vnpay'),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Xử lý kết quả thanh toán thành công',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 200),
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', nullable: true, example: null),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'order_id', type: 'integer', example: 1),
                                new OA\Property(property: 'order_status', type: 'string', example: 'CONFIRMED'),
                                new OA\Property(property: 'payment_status', type: 'string', example: 'PAID'),
                                new OA\Property(property: 'transaction_id', type: 'string', example: '120260623120000'),
                                new OA\Property(property: 'response_code', type: 'string', example: '00'),
                                new OA\Property(property: 'transaction_status', type: 'string', example: '00'),
                            ],
                            type: 'object'
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 404, description: 'Không tìm thấy giao dịch thanh toán'),
            new OA\Response(response: 422, description: 'Chữ ký không hợp lệ hoặc dữ liệu không khớp'),
        ]
    )]
    public function return(Request $request)
    {
        $result = $this->vnpayService->handleReturn($request->query());

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => $result,
        ]);
    }
}
