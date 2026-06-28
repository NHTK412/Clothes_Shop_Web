<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class CustomerController extends Controller
{
    #[OA\Get(
        path: '/api/customers',
        operationId: 'listCustomers',
        summary: 'Danh sách khách hàng',
        description: 'Danh sách khách hàng dành cho quản trị viên.',
        security: [['bearerAuth' => []]],
        tags: ['Khách hàng'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', description: 'Tìm theo tên, email hoặc số điện thoại', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', description: 'Lọc theo trạng thái', required: false, schema: new OA\Schema(type: 'string', enum: ['ACTIVE', 'INACTIVE'])),
            new OA\Parameter(name: 'per_page', in: 'query', description: 'Số lượng mỗi trang', required: false, schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Lấy danh sách khách hàng thành công'),
            new OA\Response(response: 401, description: 'Chưa xác thực'),
            new OA\Response(response: 403, description: 'Chỉ quản trị viên được phép'),
        ]
    )]
    public function index(Request $request)
    {
        $this->ensureAdmin($request->user());

        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'status' => ['nullable', 'string', Rule::in(['ACTIVE', 'INACTIVE'])],
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = User::query()
            ->where('role', 'ROLE_CUSTOMER')
            ->withCount('orders')
            ->withSum('orders', 'final_price');

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $customers = $query->orderByDesc('created_at')
            ->paginate($validated['per_page'] ?? 15);

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => [
                'data' => $customers->getCollection()->map(fn ($customer) => $this->customerData($customer)),
                'pagination' => [
                    'current_page' => $customers->currentPage(),
                    'per_page' => $customers->perPage(),
                    'total' => $customers->total(),
                    'last_page' => $customers->lastPage(),
                ],
            ],
        ]);
    }

    #[OA\Get(
        path: '/api/customers/{customer}',
        operationId: 'showCustomer',
        summary: 'Chi tiết khách hàng',
        description: 'Xem thông tin chi tiết một khách hàng.',
        security: [['bearerAuth' => []]],
        tags: ['Khách hàng'],
        responses: [
            new OA\Response(response: 200, description: 'Lấy thông tin khách hàng thành công'),
            new OA\Response(response: 401, description: 'Chưa xác thực'),
            new OA\Response(response: 403, description: 'Chỉ quản trị viên được phép'),
            new OA\Response(response: 404, description: 'Không tìm thấy khách hàng'),
        ]
    )]
    public function show(Request $request, User $customer)
    {
        $this->ensureAdmin($request->user());

        $customer->loadCount('orders')->loadSum('orders', 'final_price');

        return $this->success($this->customerData($customer));
    }

    #[OA\Patch(
        path: '/api/customers/{customer}',
        operationId: 'updateCustomer',
        summary: 'Cập nhật khách hàng',
        description: 'Cập nhật thông tin trạng thái, vai trò hoặc số điện thoại của khách hàng.',
        security: [['bearerAuth' => []]],
        tags: ['Khách hàng'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', nullable: true, example: 'Nguyễn Văn A'),
                    new OA\Property(property: 'phone', type: 'string', nullable: true, example: '0901234567'),
                    new OA\Property(property: 'status', type: 'string', enum: ['ACTIVE', 'INACTIVE'], example: 'ACTIVE'),
                    new OA\Property(property: 'role', type: 'string', enum: ['ROLE_CUSTOMER', 'ROLE_ADMIN'], example: 'ROLE_CUSTOMER'),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Cập nhật khách hàng thành công'),
            new OA\Response(response: 401, description: 'Chưa xác thực'),
            new OA\Response(response: 403, description: 'Chỉ quản trị viên được phép'),
            new OA\Response(response: 422, description: 'Dữ liệu không hợp lệ'),
        ]
    )]
    public function update(Request $request, User $customer)
    {
        $this->ensureAdmin($request->user());

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'phone' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('users', 'phone')->ignore($customer->id),
            ],
            'status' => ['nullable', 'string', Rule::in(['ACTIVE', 'INACTIVE'])],
            'role' => ['nullable', 'string', Rule::in(['ROLE_CUSTOMER', 'ROLE_ADMIN'])],
        ]);

        $customer->fill($validated);
        $customer->save();

        return $this->success($this->customerData($customer->fresh()));
    }

    #[OA\Delete(
        path: '/api/customers/{customer}',
        operationId: 'deleteCustomer',
        summary: 'Vô hiệu hóa khách hàng',
        description: 'Đặt trạng thái khách hàng thành INACTIVE.',
        security: [['bearerAuth' => []]],
        tags: ['Khách hàng'],
        responses: [
            new OA\Response(response: 200, description: 'Vô hiệu hóa khách hàng thành công'),
            new OA\Response(response: 401, description: 'Chưa xác thực'),
            new OA\Response(response: 403, description: 'Chỉ quản trị viên được phép'),
            new OA\Response(response: 404, description: 'Không tìm thấy khách hàng'),
        ]
    )]
    public function destroy(Request $request, User $customer)
    {
        $this->ensureAdmin($request->user());

        $customer->update(['status' => 'INACTIVE']);

        return $this->success($this->customerData($customer->fresh()));
    }

    private function ensureAdmin(?User $user): void
    {
        if (! $user || $user->role !== 'ROLE_ADMIN') {
            abort(403, 'Chỉ quản trị viên được phép');
        }
    }

    private function customerData(User $user): array
    {
        $totalOrders = $user->orders_count ?? $user->orders()->count();
        $totalSpent = $user->orders_final_price_sum ?? $user->orders()->sum('final_price');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar' => $user->avatar,
            'role' => $user->role,
            'status' => $user->status,
            'total_orders' => (int) $totalOrders,
            'total_spent' => (float) $totalSpent,
            'created_at' => $user->created_at?->toISOString(),
        ];
    }

    private function success(array $data)
    {
        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => $data,
        ]);
    }
}
