<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
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
        return view('auth.login');
    }

    public function authenticate(LoginRequest $request)
    {
        $email = $request->input('email');
        $password = $request->input('password');
        if (! $this->authService->authenticate($email, $password)) {
            return back()->withErrors(
                [
                    'login' => 'Thông tin đăng nhập không hợp lệ.',
                ]);
        }

        return 'Đăng nhập thành công!';
    }

    public function logout()
    {
        $this->authService->logout();

        return redirect()->route('login');
    }

    public function registerFormUI()
    {
        return view('auth.register');
    }

    public function register(RegisterRequest $request)
    {
        $newUser = $this->authService->register(
            $request->input('name'),
            $request->input('email'),
            $request->input('phone'),
            $request->input('password'),
        );

        if (! $newUser) {
            return back()->withErrors(
                [
                    'register' => 'Đăng ký thất bại, vui lòng thử lại.',
                ]);
        }

        return redirect()->route('login');
    }

    public function index()
    {
        if (! $this->authService->checkLogin()) {
            return redirect()->route('login');
        }

        return 'Bạn đã đăng nhập!';
    }
}
