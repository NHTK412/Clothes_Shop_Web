<?php

namespace App\Http\Services;

use App\Models\OauthProvider;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
        if (User::where('email', $email)->exists()) {
            $user = User::where('email', $email)->first();
            $user->password = bcrypt($password);
            $user->save();

            return $user;
        } else {
            return User::create([
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'password' => bcrypt($password),
            ]);
        }
    }

    public function oauth2Login($provider, $uid, $email, $name = null)
    {
        return DB::transaction(function () use ($provider, $uid, $email, $name) {
            // Kiểm tra xem người dùng đã tồn tại trong cơ sở dữ liệu chưa
            $oauthProvider = OauthProvider::where('provider', $provider)
                ->where('provider_id', $uid)
                ->first();
            if ($oauthProvider) {
                // Nếu người dùng đã tồn tại, đăng nhập người dùng đó
                $user = $oauthProvider->user;
                $token = auth()->login($user); // Đăng nhập người dùng

                return [
                    'access_token' => $token,
                    'expires_in' => Auth::factory()->getTTL() * 60,
                    'user' => Auth::user(),
                ];
            } else {
                if (User::where('email', $email)->exists()) {
                    // Nếu người dùng đã tồn tại nhưng chưa liên kết với OAuth2, liên kết tài khoản
                    $user = User::where('email', $email)->first();
                    $user->oauthProviders()->create([
                        'provider' => $provider,
                        'provider_id' => $uid,
                    ]);
                    $token = auth()->login($user); // Đăng nhập người dùng

                    return [
                        'access_token' => $token,
                        'expires_in' => Auth::factory()->getTTL() * 60,
                        'user' => Auth::user(),
                    ];
                } else {
                    // Nếu người dùng chưa tồn tại, tạo một người dùng mới
                    $user = User::create([
                        'email' => $email,
                        'name' => $name,
                        'password' => null, // Không cần mật khẩu cho đăng nhập OAuth2
                    ]);
                    // Tạo một bản ghi OauthProvider mới liên kết với người dùng
                    $user->oauthProviders()->create([
                        'provider' => $provider,
                        'provider_id' => $uid,
                    ]);
                    $token = auth()->login($user); // Đăng nhập người dùng

                    return [
                        'access_token' => $token,
                        'expires_in' => Auth::factory()->getTTL() * 60,
                        'user' => Auth::user(),
                    ];
                }
            }

        });
    }
}
