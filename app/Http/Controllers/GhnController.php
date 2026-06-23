<?php

namespace App\Http\Controllers;

use App\Http\Services\CartService;
use App\Models\Order;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use OpenApi\Attributes as OA;
use Throwable;

class GhnController extends Controller
{
    private CartService $cartService;

    public function __construct(CartService $cartService)
    {
        $this->cartService = $cartService;
    }

    #[OA\Get(
        path: '/api/ghn/provinces',
        operationId: 'getGhnProvinces',
        summary: 'Lấy danh sách tỉnh/thành từ GHN',
        description: 'Chuyển tiếp API GHN và chỉ trả về các trường cần thiết gồm mã tỉnh, tên tỉnh và mã code.',
        tags: ['GHN'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lấy danh sách tỉnh/thành thành công',
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
                                            new OA\Property(property: 'province_id', type: 'integer', example: 202),
                                            new OA\Property(property: 'province_name', type: 'string', example: 'Hồ Chí Minh'),
                                            new OA\Property(property: 'code', type: 'string', example: '202'),
                                        ],
                                        type: 'object'
                                    )
                                ),
                                new OA\Property(property: 'pagination', type: 'object', nullable: true, example: null),
                            ],
                            type: 'object'
                        ),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function provinces()
    {
        // $response = $this->get('/shiip/public-api/master-data/province');
        // https://dev-online-gateway.ghn.vn/shiip/public-api/v3/master-data/province/all
        $response = $this->get('/shiip/public-api/v3/master-data/province/all');

        if (! $response instanceof Response) {
            return $response;
        }

        return $this->success($this->data($response)->map(function ($province) {
            return [
                'province_id' => $province['_id'] ?? null,
                'province_name' => $province['name'] ?? null,
                'code' => $province['_id'] ?? null,
            ];
        })->values()->toArray());
    }

    #[OA\Get(
        path: '/api/ghn/districts',
        operationId: 'getGhnDistricts',
        summary: 'Lấy danh sách quận/huyện từ GHN',
        description: 'Lấy danh sách quận/huyện theo mã tỉnh/thành và chỉ trả về các trường cần thiết.',
        tags: ['GHN'],
        parameters: [
            new OA\Parameter(name: 'province_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer'), example: 202),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lấy danh sách quận/huyện thành công'),
            new OA\Response(response: 422, description: 'Dữ liệu không hợp lệ'),
        ]
    )]
    public function districts(Request $request)
    {
        $validated = $request->validate([
            'province_id' => 'required|integer',
        ]);

        $response = $this->get('/shiip/public-api/master-data/district', [
            'province_id' => $validated['province_id'],
        ]);

        if (! $response instanceof Response) {
            return $response;
        }

        return $this->success($this->data($response)->map(function ($district) {
            return [
                'district_id' => $district['DistrictID'] ?? null,
                'province_id' => $district['ProvinceID'] ?? null,
                'district_name' => $district['DistrictName'] ?? null,
                'code' => $district['Code'] ?? null,
            ];
        })->values()->toArray());
    }

    #[OA\Get(
        path: '/api/ghn/wards',
        operationId: 'getGhnWards',
        summary: 'Lấy danh sách phường/xã từ GHN',
        description: 'Lấy danh sách phường/xã theo mã quận/huyện và chỉ trả về các trường cần thiết.',
        tags: ['GHN'],
        parameters: [
            // new OA\Parameter(name: 'district_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer'), example: 3695),
            new OA\Parameter(name: 'province_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer'), example: 3695),

        ],
        responses: [
            new OA\Response(response: 200, description: 'Lấy danh sách phường/xã thành công'),
            new OA\Response(response: 422, description: 'Dữ liệu không hợp lệ'),
        ]
    )]
    public function wards(Request $request)
    {
        $validated = $request->validate([
            'province_id' => 'required|integer',
        ]);

        $response = $this->get('/shiip/public-api/v3/master-data/ward/all-by-province-id', [
            'province_id' => $validated['province_id'],
        ]);

        if (! $response instanceof Response) {
            return $response;
        }

        return $this->success($this->data($response)->map(function ($ward) {
            return [
                'ward_code' => $ward['_id'] ?? null,
                'district_id' => $ward['parent_id'] ?? null,
                'ward_name' => $ward['name'] ?? null,
            ];
        })->values()->toArray());
    }

    #[OA\Get(
        path: '/api/ghn/shipping-fee',
        operationId: 'calculateGhnShippingFee',
        summary: 'Tính phí giao hàng GHN',
        description: 'Tính phí giao hàng bằng GHN với thông số gói hàng mặc định cho sản phẩm quần áo. Danh sách sản phẩm được lấy từ giỏ hàng của người dùng đang đăng nhập.',
        security: [['bearerAuth' => []]],
        tags: ['GHN'],
        parameters: [
            new OA\Parameter(name: 'to_ward_id_v2', in: 'query', required: true, schema: new OA\Schema(type: 'integer'), example: 1003544),
            new OA\Parameter(name: 'to_district_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer'), example: 1000001),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Tính phí giao hàng thành công',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 200),
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', nullable: true, example: null),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'total', type: 'integer', example: 49500),
                            ],
                            type: 'object'
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Chưa xác thực'),
            new OA\Response(response: 422, description: 'Dữ liệu không hợp lệ'),
        ]
    )]
    public function shippingFee(Request $request)
    {
        $validated = $request->validate([
            'to_ward_id_v2' => 'required|integer',
            'to_district_id' => 'required|integer',
        ]);

        $items = $this->cartService->getShippingItems($request->user());

        if (empty($items)) {
            return $this->error('Cart is empty.', 422);
        }

        $payload = [
            'service_type_id' => (int) config('services.ghn.default_service_type_id'),
            'is_new_to_address' => true,
            'to_ward_id_v2' => (int) $validated['to_ward_id_v2'],
            'to_district_id' => (int) $validated['to_district_id'],
            'weight' => (int) config('services.ghn.default_weight'),
            'length' => (int) config('services.ghn.default_length'),
            'width' => (int) config('services.ghn.default_width'),
            'height' => (int) config('services.ghn.default_height'),
            'items' => $items,
        ];

        $response = $this->post('/shiip/public-api/v2/shipping-order/fee', $payload);

        if (! $response instanceof Response) {
            return $response;
        }

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => [
                'total' => $response->json('data.total'),
            ],
        ], 200);
    }

    #[OA\Get(
        path: '/api/ghn/detail',
        operationId: 'getGhnOrderDetail',
        summary: 'Lấy chi tiết đơn giao hàng GHN',
        description: 'Lấy lịch sử trạng thái đơn giao hàng từ GHN theo mã vận đơn order_code. Chỉ người dùng sở hữu đơn hàng mới được xem.',
        tags: ['GHN'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'order_code',
                description: 'Mã vận đơn GHN.',
                in: 'query',
                required: true,
                schema: new OA\Schema(type: 'string'),
                example: 'LX8E8H'
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lấy chi tiết đơn giao hàng thành công',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 200),
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', nullable: true, example: null),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'status', type: 'string', example: 'picking'),
                                    new OA\Property(property: 'payment_type_id', type: 'integer', example: 1),
                                    new OA\Property(property: 'trip_code', type: 'string', example: ''),
                                    new OA\Property(property: 'updated_date', type: 'string', format: 'date-time', example: '2026-06-21T10:27:27.812Z'),
                                ],
                                type: 'object'
                            )
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 400,
                description: 'GHN trả lỗi hoặc mã vận đơn không hợp lệ',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 400),
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'GHN request failed.'),
                        new OA\Property(property: 'data', type: 'object', nullable: true, example: null),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Thiếu hoặc sai dữ liệu order_code',
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
            new OA\Response(
                response: 502,
                description: 'Không thể kết nối GHN',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 502),
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'cURL error message'),
                        new OA\Property(property: 'data', type: 'object', nullable: true, example: null),
                    ],
                    type: 'object'
                )
            ),
        ]
    )]
    public function detail(Request $request)
    {
        $validated = $request->validate([
            'order_code' => 'required|string',
        ]);

        Order::where('user_id', $request->user()->id)
            ->where('ghn_order_code', $validated['order_code'])
            ->firstOrFail();

        $response = $this->get('/shiip/public-api/v2/shipping-order/detail', [
            'order_code' => $validated['order_code'],
        ]);

        if (! $response instanceof Response) {
            return $response;
        }

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => collect($response->json('data.log', []))
                ->map(fn ($log) => [
                    'status' => $log['status'] ?? null,
                    'payment_type_id' => $log['payment_type_id'] ?? null,
                    'trip_code' => $log['trip_code'] ?? '',
                    'updated_date' => $log['updated_date'] ?? null,
                ])
                ->values()
                ->toArray(),
        ], 200);
    }

    private function get(string $path, array $query = [])
    {
        try {
            $response = $this->client()->get($path, $query);
        } catch (Throwable $exception) {
            return $this->error($exception->getMessage(), 502);
        }

        return $this->handleGhnResponse($response);
    }

    private function post(string $path, array $payload = [])
    {
        if (! config('services.ghn.shop_id')) {
            return $this->error('GHN shop id is not configured.', 500);
        }

        try {
            $response = $this->client()
                ->withHeaders(['ShopId' => config('services.ghn.shop_id')])
                ->post($path, $payload);
        } catch (Throwable $exception) {
            return $this->error($exception->getMessage(), 502);
        }

        return $this->handleGhnResponse($response);
    }

    private function client()
    {
        $token = config('services.ghn.token');

        if (! $token) {
            throw new \RuntimeException('GHN token is not configured.');
        }

        return Http::baseUrl(config('services.ghn.base_url'))
            ->withHeaders(['Token' => $token])
            ->acceptJson()
            ->withOptions([
                'verify' => config('services.ghn.verify_ssl'),
            ])
            ->timeout(15);
    }

    private function handleGhnResponse(Response $response)
    {
        $body = $response->json();

        if (! $response->successful() || ($body['code'] ?? null) !== 200) {
            return $this->error($body['message'] ?? 'GHN request failed.', $response->successful() ? 400 : $response->status());
        }

        return $response;
    }

    private function data(Response $response)
    {
        return collect($response->json('data', []));
    }

    private function success(array $items)
    {
        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => [
                'items' => $items,
                'pagination' => null,
            ],
        ], 200);
    }

    private function error(string $message, int $status)
    {
        return response()->json([
            'status' => $status,
            'success' => false,
            'message' => $message,
            'data' => null,
        ], $status);
    }
}
