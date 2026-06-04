<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Services\AuthService;

class AuthController extends Controller
{
    private AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login()
    {
        // $password = bcrypt('Tuankhang412@');
        // dd($password);

        return view('auth.login');
    }

    public function authenticate(LoginRequest $request)
    {
        $email = $request->input('email');
        $password = $request->input('password');

        // Hask password trước khi xác thực

        if (! $this->authService->authenticate($email, $password)) {
            return back()->withErrors(
                [
                    'login' => 'Thông tin đăng nhập không hợp lệ.',
                ]);
        }

        // Tạo session
        return 'Đăng nhập thành công!';
    }

    public function index()
    {
        if (! $this->authService->checkLogin()) {
            return redirect()->route('login');
        }

        return 'Bạn đã đăng nhập!';
    }

    public function logout()
    {
        $this->authService->logout();

        return redirect()->route('login');
    }
}
