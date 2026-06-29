<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class UserProfileController extends Controller
{
    #[OA\Get(
        path: '/api/profile',
        operationId: 'getCurrentUserProfile',
        summary: 'Lấy thông tin cá nhân',
        description: 'Lấy thông tin cá nhân của người dùng đang đăng nhập.',
        security: [['bearerAuth' => []]],
        tags: ['Thông tin cá nhân'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Lấy thông tin cá nhân thành công',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'integer', example: 200),
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', nullable: true, example: null),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', nullable: true, example: 'Nguyễn Văn A'),
                                new OA\Property(property: 'email', type: 'string', nullable: true, example: 'user@example.com'),
                                new OA\Property(property: 'phone', type: 'string', nullable: true, example: '0901234567'),
                                new OA\Property(property: 'avatar', type: 'string', nullable: true, example: 'users/avatar.jpg'),
                                new OA\Property(property: 'role', type: 'string', example: 'ROLE_CUSTOMER'),
                                new OA\Property(property: 'status', type: 'string', example: 'ACTIVE'),
                            ],
                            type: 'object'
                        ),
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(response: 401, description: 'Chưa xác thực'),
        ]
    )]
    public function show(Request $request)
    {
        return $this->success($this->profileData($request->user()));
    }

    #[OA\Put(
        path: '/api/profile',
        operationId: 'updateCurrentUserProfile',
        summary: 'Cập nhật thông tin cá nhân',
        description: 'Cập nhật thông tin cá nhân của người dùng đang đăng nhập. Endpoint này không cho phép đổi mật khẩu.',
        security: [['bearerAuth' => []]],
        tags: ['Thông tin cá nhân'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', nullable: true, example: 'Nguyễn Văn A'),
                    new OA\Property(property: 'phone', type: 'string', nullable: true, example: '0901234567'),
                    new OA\Property(property: 'avatar', type: 'string', nullable: true, example: 'users/avatar.jpg'),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Cập nhật thông tin cá nhân thành công'),
            new OA\Response(response: 401, description: 'Chưa xác thực'),
            new OA\Response(response: 422, description: 'Dữ liệu không hợp lệ'),
        ]
    )]
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|nullable|string|max:255',
            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                Rule::unique('users', 'phone')->ignore($user->id),
            ],
            'avatar' => 'sometimes|nullable|string|max:255',
        ]);

        $user->fill($validated);
        $user->save();

        return $this->success($this->profileData($user->fresh()));
    }

    #[OA\Patch(
        path: '/api/profile',
        operationId: 'patchCurrentUserProfile',
        summary: 'Cập nhật một phần thông tin cá nhân',
        description: 'Cập nhật một phần thông tin cá nhân của người dùng đang đăng nhập. Endpoint này không cho phép đổi mật khẩu.',
        security: [['bearerAuth' => []]],
        tags: ['Thông tin cá nhân'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', nullable: true, example: 'Nguyễn Văn A'),
                    new OA\Property(property: 'phone', type: 'string', nullable: true, example: '0901234567'),
                    new OA\Property(property: 'avatar', type: 'string', nullable: true, example: 'users/avatar.jpg'),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Cập nhật thông tin cá nhân thành công'),
            new OA\Response(response: 401, description: 'Chưa xác thực'),
            new OA\Response(response: 422, description: 'Dữ liệu không hợp lệ'),
        ]
    )]
    public function patch(Request $request)
    {
        return $this->update($request);
    }

    #[OA\Delete(
        path: '/api/profile',
        operationId: 'deleteCurrentUserProfile',
        summary: 'Xóa tài khoản hiện tại',
        description: 'Xóa tài khoản của người dùng đang đăng nhập.',
        security: [['bearerAuth' => []]],
        tags: ['Thông tin cá nhân'],
        responses: [
            new OA\Response(response: 200, description: 'Xóa tài khoản thành công'),
            new OA\Response(response: 401, description: 'Chưa xác thực'),
        ]
    )]
    public function destroy(Request $request)
    {
        // $request->user()->delete();

        $request->user()->update(['status' => 'INACTIVE']);

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => null,
        ]);
    }

    private function profileData($user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar' => $user->avatar,
            'role' => $user->role,
            'status' => $user->status,
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
