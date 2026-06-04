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
    @vite(['resources/css/auth/reset-password.css'])
    <title>Khôi phục mật khẩu</title>
</head>

<body class="min-h-screen flex flex-col">
    @include('layout.header')
    <main class="flex-grow flex items-center justify-center py-xl px-sm">
        <div class="w-full max-w-[480px]">
            <div class="bg-surface-container-lowest border border-outline-variant p-lg rounded shadow-sm">
                <div class="text-center mb-lg">
                    <div
                        class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-secondary-container text-primary mb-sm">
                        <span class="material-symbols-outlined text-[32px]">lock_reset</span>
                    </div>
                    <h1 class="font-headline-md text-headline-md text-on-surface mb-xs">Quên mật khẩu?</h1>
                    <p class="font-body-sm text-body-sm text-on-surface-variant">
                        Nhập email bạn đã đăng ký để nhận hướng dẫn khôi phục mật khẩu.
                    </p>
                </div>
                <form class="space-y-md" id="forgotPasswordForm">
                    <div class="space-y-xs">
                        <label
                            class="font-label-sm text-label-sm text-on-surface-variant block uppercase tracking-wider"
                            for="email">Địa chỉ Email</label>
                        <div class="relative group">
                            <input
                                class="w-full px-sm py-md bg-surface-container-lowest border border-outline-variant rounded focus:border-primary focus:ring-3 focus:ring-primary/10 outline-none transition-all duration-200 font-body-md text-body-md placeholder:text-outline"
                                id="email" placeholder="name@example.com" required="" type="email" />
                            <span
                                class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-outline group-focus-within:text-primary transition-colors">mail</span>
                        </div>
                    </div>
                    <button
                        class="w-full bg-primary text-on-primary py-md px-lg rounded font-label-md text-label-md hover:bg-primary-container transition-all duration-200 active:scale-[0.98] flex justify-center items-center gap-xs"
                        type="submit">
                        Gửi yêu cầu
                        <span class="material-symbols-outlined text-[18px]">send</span>
                    </button>
                    <div class="hidden animate-in fade-in slide-in-from-top-2 duration-300 p-sm bg-secondary-container/30 border border-secondary-container rounded flex items-start gap-sm"
                        id="successMessage">
                        <span class="material-symbols-outlined text-primary">check_circle</span>
                        <p class="font-body-sm text-body-sm text-on-secondary-container">
                            Một email hướng dẫn đã được gửi đến hộp thư của bạn. Vui lòng kiểm tra (bao gồm cả thư rác).
                        </p>
                    </div>
                </form>
                <div class="mt-lg pt-md border-t border-outline-variant text-center">
                    <a class="inline-flex items-center gap-xs font-label-md text-label-md text-secondary hover:text-primary transition-colors group"
                        href="{{ route('login') }}">
                        <span
                            class="material-symbols-outlined text-[18px] group-hover:-translate-x-1 transition-transform">arrow_back</span>
                        Quay lại Đăng nhập
                    </a>
                </div>
            </div>
            <div class="mt-xl hidden md:grid grid-cols-3 gap-sm opacity-20 pointer-events-none">
                <div class="h-1 bg-primary-fixed rounded-full"></div>
                <div class="h-1 bg-secondary-fixed rounded-full"></div>
                <div class="h-1 bg-tertiary-fixed rounded-full"></div>
            </div>
        </div>
    </main>
    @include('layout.footer')
</body>

</html>
