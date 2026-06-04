@vite('resources/css/app.css')
<!DOCTYPE html>

<html lang="vi">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
        rel="stylesheet" />
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap"
        rel="stylesheet" />
    <link rel="shortcut icon" href="{{ asset('images/icon_logo.png') }}" type="image/x-icon">
    @vite(['resources/css/app.css', 'resources/js/auth/login.js'])
    <title>Đăng nhập</title>
</head>

<body class="min-h-screen flex flex-col">
    @include('layout.header')
    <main class="flex-grow flex items-center justify-center py-xl px-margin-mobile">
        <div
            class="w-full max-w-[480px] bg-surface-container-lowest login-card rounded-xl p-lg border border-outline-variant">
            <div class="text-center mb-lg">
                <h1 class="font-headline-md text-headline-md text-on-background mb-xs">Chào Mừng Trở Lại</h1>
                <p class="font-body-sm text-body-sm text-secondary">Vui lòng đăng nhập để tiếp tục mua sắm</p>
            </div>
            <div class="grid grid-cols-1 gap-sm mb-lg">
                <button
                    class="flex items-center justify-center gap-xs py-sm border border-outline-variant rounded-lg hover:bg-surface-container-low hover:cursor-pointer transition-colors duration-200 group">
                    <svg class="w-5 h-5" viewbox="0 0 24 24">
                        <path
                            d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                            fill="#4285F4"></path>
                        <path
                            d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                            fill="#34A853"></path>
                        <path
                            d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z"
                            fill="#FBBC05"></path>
                        <path
                            d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 12-4.53z"
                            fill="#EA4335"></path>
                    </svg>
                    <iconify-icon icon="logos:google-icon"></iconify-icon>
                    <span class="font-label-md text-label-md text-on-surface">Google</span>
                </button>
            </div>
            <div class="relative flex items-center mb-lg">
                <div class="flex-grow border-t border-outline-variant"></div>
                <span class="flex-shrink mx-sm font-label-sm text-label-sm text-secondary">Hoặc bằng Email</span>
                <div class="flex-grow border-t border-outline-variant"></div>
            </div>
            <form action="{{ route('login.authenticate') }}" class="space-y-md" method="POST">
                @csrf
                <div class="flex flex-col gap-xs">
                    <label class="font-label-sm text-label-sm text-on-surface" for="email">Email của bạn</label>
                    <input
                        class="w-full px-sm py-md bg-surface-container-lowest border border-outline-variant rounded-lg focus:ring-4 focus:ring-primary/10 focus:border-primary transition-all duration-200 outline-none text-body-md"
                        id="email" placeholder="example@luxe.com" required="" type="email" name="email" />
                </div>
                <div class="flex flex-col gap-xs">
                    <div class="flex justify-between items-center">
                        <label class="font-label-sm text-label-sm text-on-surface" for="password">Mật khẩu</label>
                        <a class="font-label-sm text-label-sm text-primary hover:underline" href="#">Quên mật
                            khẩu?</a>
                    </div>
                    <div class="relative">
                        <input
                            class="w-full px-sm py-md bg-surface-container-lowest border border-outline-variant rounded-lg focus:ring-4 focus:ring-primary/10 focus:border-primary transition-all duration-200 outline-none text-body-md"
                            id="password" placeholder="••••••••" required="" type="password" name="password" />
                        <button class="absolute right-sm top-1/2 -translate-y-1/2 text-secondary hover:text-primary"
                            type="button" id="toggle-password">
                            <span class="material-symbols-outlined text-[20px]" id="password-icon">visibility</span>
                        </button>
                    </div>
                </div>
                @error('login')
                    <div class="text-red-500 text-sm mb-4">
                        {{ $message }}
                    </div>
                @enderror
                <button
                    class="w-full py-md bg-primary text-on-primary font-label-md text-label-md rounded-lg hover:bg-primary-container transition-all hover:cursor-pointer duration-200 active:scale-[0.98]"
                    type="submit">
                    Đăng nhập
                </button>
            </form>
            <div class="text-center mt-lg">
                <p class="font-body-sm text-body-sm text-secondary">
                    Bạn chưa có tài khoản?
                    <a class="text-primary font-bold hover:underline" href="#">Đăng ký ngay</a>
                </p>
            </div>
        </div>
    </main>
    @include('layout.footer')
</body>

</html>
