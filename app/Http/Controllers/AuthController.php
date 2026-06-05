<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    private AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

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
