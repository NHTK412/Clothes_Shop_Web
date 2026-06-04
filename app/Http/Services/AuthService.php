<?php

namespace App\Http\Services;

class AuthService
{
    public function authenticate($email, $password): bool
    {
        $credentials = [
            'email' => $email,
            'password' => $password,
        ];

        return auth()->attempt($credentials);
    }

    public function logout()
    {
        auth()->logout();
    }

    public function checkLogin(): bool
    {
        return auth()->check();
    }
}
