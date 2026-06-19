<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    private AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    #[OA\Post(
        path: '/api/auth/login',
        summary: 'Đăng nhập',
        tags: ['Xác thực'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Đăng nhập thành công'),
            new OA\Response(response: 401, description: 'Email hoặc mật khẩu không đúng'),
            new OA\Response(response: 422, description: 'Dữ liệu không hợp lệ'),
        ]
    )]
    public function authenticateApi(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');

        $token = Auth::attempt($credentials);

        if (! $token) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sai tài khoản hoặc mật khẩu',
            ], 401);
        }

        return response()->json([
            'status' => 'success',
            'message' => null,
            'data' => [
                'access_token' => $token,
                'expires_in' => Auth::factory()->getTTL() * 60,
                'user' => Auth::user(),
            ],
        ], 200);
    }

    #[OA\Post(
        path: '/api/auth/register',
        summary: 'Đăng ký tài khoản',
        tags: ['Xác thực'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'phone', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Nguyễn Văn A'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                    new OA\Property(property: 'phone', type: 'string', example: '0901234567'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'password123'),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Đăng ký thành công'),
            new OA\Response(response: 400, description: 'Đăng ký thất bại'),
            new OA\Response(response: 422, description: 'Dữ liệu không hợp lệ'),
        ]
    )]
    public function registerApi(RegisterRequest $request)
    {
        $newUser = $this->authService->register(
            $request->input('name'),
            $request->input('email'),
            $request->input('phone'),
            $request->input('password'),
        );

        if (! $newUser) {
            return response()->json([
                'status' => 'error',
                'message' => 'Đăng ký thất bại, vui lòng thử lại.',
            ], 400);
        }

        return response()->json([
            'status' => 'success',
            'message' => null,
            'data' => $newUser,
        ], 201);
    }

    #[OA\Post(
        path: '/api/auth/send-reset-link',
        summary: 'Gửi liên kết đặt lại mật khẩu',
        tags: ['Xác thực'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(response: 204, description: 'Đã gửi liên kết đặt lại mật khẩu'),
            new OA\Response(response: 404, description: 'Không tìm thấy email'),
            new OA\Response(response: 422, description: 'Dữ liệu không hợp lệ'),
        ]
    )]
    public function sendResetPasswordLinkApi(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $status = Password::sendResetLink(
            ['email' => $request->email]
        );

        return $status === Password::RESET_LINK_SENT
            ? response()->json([
                'status' => 'success',
                'message' => null,
            ], 204)
            : response()->json([
                'status' => 'error',
                'message' => 'Không tìm thấy email',
            ], 404);
    }

    #[OA\Post(
        path: '/api/auth/reset-password',
        summary: 'Đặt lại mật khẩu',
        tags: ['Xác thực'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['token', 'email', 'password', 'password_confirmation'],
                properties: [
                    new OA\Property(property: 'token', type: 'string'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'newpassword123'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'newpassword123'),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(response: 204, description: 'Đặt lại mật khẩu thành công'),
            new OA\Response(response: 400, description: 'Đặt lại mật khẩu thất bại'),
            new OA\Response(response: 422, description: 'Dữ liệu không hợp lệ'),
        ]
    )]
    public function resetPasswordApi(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => bcrypt($password),
                ])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json([
                'status' => 'success',
                'message' => null,
            ], 204)
            : response()->json([
                'status' => 'error',
                'message' => 'Đặt lại mật khẩu thất bại',
            ], 400);
    }
}
