<?php

namespace App\Http\Services;
use App\Models\User;
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

    public function register(
        string $name,
        string $email,
        string $phone,
        string $password
    ) {
        return User::create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'password' => bcrypt($password),
        ]);
    }
}
