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
    @vite(['resources/css/auth/register.css'])
    <title>Đăng ký</title>
</head>

<body class="min-h-screen flex flex-col">
    @include('layout.header')
    <main class="flex-grow flex items-center justify-center pt-[30px] pb-xl px-margin-mobile">
        <div class="max-w-max-width w-full grid grid-cols-1 md:grid-cols-12 gap-gutter items-center">
            <div class="hidden md:block md:col-span-6 relative h-[600px] overflow-hidden rounded-xl">
                <img alt="Fashion Lifestyle" class="absolute inset-0 w-full h-full object-cover"
                    data-alt="A high-end fashion lifestyle image featuring a sophisticated woman walking through a sun-drenched, minimalist architectural space. The color palette is dominated by soft whites and cool blues, reflecting the brand identity. The lighting is bright and airy, creating a professional and premium boutique atmosphere. High-fashion aesthetics are combined with clean lines and a sense of luxury commerce."
                    src="https://noithattugia.com/wp-content/uploads/Dien-tich-nho-nhung-shop-quan-ao-van-mang-lai-cam-giac-nhe-nhang-loi-loi-di-thong-thoang-thoai-mai-nhat.jpg" />
                <div class="absolute inset-0 bg-black/40 backdrop-blur-[2px]"></div>
                <div class="absolute inset-0 flex flex-col  justify-end text-white px-lg">
                    <h2 class="text-display-lg font-display-lg mb-sm">Tham gia ngay</h2>
                    <p class="mb-10">Trở thành thành viên ngay hôm nay để nhận
                        được những ưu đãi đặc biệt và quyền lợi độc quyền.</p>
                </div>
            </div>
            <div class="md:col-span-6 flex justify-center">
                <div
                    class="w-full max-w-[480px] bg-surface-container-lowest p-lg rounded-xl border border-outline-variant shadow-sm transition-all duration-300 hover:border-primary">
                    <div class="mb-lg">
                        <h1 class="text-headline-md font-headline-md text-primary mb-xs">Tạo tài khoản mới</h1>
                        <p class="text-body-sm font-body-sm text-secondary">Vui lòng điền thông tin bên dưới để bắt đầu
                            mua sắm.</p>
                    </div>
                    <form class="space-y-md" id="registerForm" action="{{ route('register.submit') }}" method="POST">
                        @csrf
                        <div class="flex flex-col gap-xs">
                            <label class="text-label-sm font-label-sm text-on-surface" for="fullname">Họ và tên</label>
                            <input
                                class="w-full px-sm py-xs bg-surface-container-lowest border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all text-body-md font-body-md"
                                id="fullname" placeholder="Nguyễn Văn A" type="text" name="name" />
                        </div>
                        <div class="flex flex-col gap-xs">
                            <label class="text-label-sm font-label-sm text-on-surface" for="email">Email</label>
                            <input
                                class="w-full px-sm py-xs bg-surface-container-lowest border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all text-body-md font-body-md"
                                id="email" placeholder="example@gmail.com" type="email" name="email" />
                        </div>
                        <div class="flex flex-col gap-xs">
                            <label class="text-label-sm font-label-sm text-on-surface" for="phone">Số điện
                                thoại</label>
                            <input
                                class="w-full px-sm py-xs bg-surface-container-lowest border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all text-body-md font-body-md"
                                id="phone" placeholder="0123 456 789" type="tel" name="phone" />
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-md">
                            <div class="flex flex-col gap-xs">
                                <label class="text-label-sm font-label-sm text-on-surface" for="password">Mật
                                    khẩu</label>
                                <input
                                    class="w-full px-sm py-xs bg-surface-container-lowest border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all text-body-md font-body-md"
                                    id="password" placeholder="••••••••" type="password" name="password" />
                            </div>
                            <div class="flex flex-col gap-xs">
                                <label class="text-label-sm font-label-sm text-on-surface" for="confirm_password">Xác
                                    nhận mật khẩu</label>
                                <input
                                    class="w-full px-sm py-xs bg-surface-container-lowest border border-outline-variant rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all text-body-md font-body-md"
                                    id="confirm_password" placeholder="••••••••" type="password"
                                    name="password_confirmation" />
                            </div>
                        </div>
                        <div class="flex items-start gap-xs py-xs">
                            <input class="mt-1 rounded border-outline-variant text-primary focus:ring-primary"
                                id="terms" type="checkbox" />
                            <label class="text-body-sm font-body-sm text-secondary" for="terms">
                                Tôi đồng ý với <a class="text-primary hover:underline" href="#">Điều khoản dịch
                                    vụ</a> và <a class="text-primary hover:underline" href="#">Chính sách bảo
                                    mật</a>.
                            </label>
                        </div>
                        @error('register')
                            <div class="text-red-500 text-sm mb-4">
                                {{ $message }}
                            </div>
                        @enderror
                        <button
                            class="w-full bg-primary text-on-primary font-label-md text-label-md py-sm rounded-lg hover:bg-primary-container hover:shadow-md transition-all duration-300 transform active:scale-95 flex justify-center items-center gap-xs"
                            type="submit">
                            Đăng ký ngay
                            <span class="material-symbols-outlined text-[20px]">arrow_forward</span>
                        </button>
                    </form>
                    <div class="mt-lg pt-lg border-t border-outline-variant flex flex-col items-center gap-sm">
                        <p class="text-body-sm font-body-sm text-secondary">Đã có tài khoản?
                            <a class="text-primary font-bold hover:underline" href="{{ route('login') }}">Đăng nhập
                                ngay</a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>
    @include('layout.footer')
</body>

</html>
