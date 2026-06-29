# Clothes Shop API

Backend API cho hệ thống bán quần áo, xây dựng bằng Laravel. Dự án hỗ trợ quản lý sản phẩm và biến thể, giỏ hàng, voucher, khuyến mãi, đơn hàng, thanh toán COD/VNPAY, giao hàng GHN, đánh giá sản phẩm và các nghiệp vụ quản trị.

## Công nghệ sử dụng

- PHP 8.3+
- Laravel 13
- MySQL hoặc SQLite
- JWT Authentication
- GHN API
- VNPAY
- Cloudinary
- OpenAPI/Swagger
- Vite và Tailwind CSS
- PHPUnit

## Chức năng chính

### Khách hàng

- Đăng ký, đăng nhập, đăng nhập OAuth2 và đặt lại mật khẩu.
- Quản lý hồ sơ và địa chỉ nhận hàng.
- Xem, tìm kiếm, lọc và sắp xếp sản phẩm.
- Lọc sản phẩm theo danh mục, khoảng giá, tồn kho và thuộc tính biến thể.
- Quản lý giỏ hàng và sản phẩm yêu thích.
- Áp dụng voucher cho giá trị đơn hàng hoặc phí vận chuyển.
- Đặt hàng bằng COD hoặc VNPAY.
- Theo dõi trạng thái đơn và thông tin vận chuyển GHN.
- Hủy đơn hợp lệ và tạo yêu cầu hoàn tiền cho VNPAY khi cần.
- Đánh giá sản phẩm trong đơn đã hoàn thành.

### Quản trị viên

- Quản lý sản phẩm, biến thể, danh mục và thuộc tính.
- Quản lý tồn kho và các phiếu nhập/xuất.
- Quản lý khách hàng.
- Quản lý voucher và chương trình khuyến mãi.
- Xem danh sách, chi tiết và thống kê doanh thu đơn hàng.
- Tải ảnh sản phẩm lên Cloudinary.

## Quy ước giá sản phẩm

`discount_price` là **số tiền được giảm**, không phải giá sau giảm.

```text
final_price = price - COALESCE(discount_price, 0)
```

Các API lọc và sắp xếp theo giá sử dụng giá thực tế sau giảm của biến thể.

## Luồng trạng thái đơn hàng

| Trạng thái | Ý nghĩa |
| --- | --- |
| `PENDING_PAYMENT` | Đơn VNPAY đã tạo và đang chờ thanh toán |
| `CONFIRMED` | Đơn COD vừa tạo hoặc đơn VNPAY đã thanh toán thành công |
| `SHIPPING` | GHN đã nhận và đang vận chuyển đơn |
| `COMPLETED` | GHN giao hàng thành công |
| `CANCELLED` | Khách hàng hủy đơn khi còn được phép |
| `RETURNED` | GHN đang hoàn hoặc đã hoàn hàng |

Quy tắc payment:

- COD được tạo với `CONFIRMED` và payment `UNPAID`.
- Khi GHN giao COD thành công, order thành `COMPLETED` và payment thành `PAID`.
- VNPAY được tạo với `PENDING_PAYMENT` và payment `UNPAID`.
- Callback VNPAY thành công chuyển order sang `CONFIRMED` và payment sang `PAID`.
- Chỉ đơn `PENDING_PAYMENT` hoặc `CONFIRMED` được hủy.
- Hủy đơn VNPAY đã thanh toán sẽ tạo refund request; payment chỉ thành `REFUNDED` sau khi tiền thực sự được hoàn.

## Yêu cầu môi trường

Máy phát triển cần có:

- PHP 8.3 trở lên.
- Composer.
- Node.js và npm.
- MySQL 8+ hoặc SQLite.
- Các PHP extension phổ biến của Laravel: OpenSSL, PDO, Mbstring, Tokenizer, XML, Ctype, JSON, cURL và Fileinfo.
- Extension GD nếu chạy toàn bộ test hoặc xử lý ảnh cục bộ.

## Cài đặt

### 1. Cài dependency

```bash
composer install
npm install
```

### 2. Tạo file môi trường

Linux/macOS:

```bash
cp .env.example .env
```

Windows PowerShell:

```powershell
Copy-Item .env.example .env
```

Tạo application key và JWT secret:

```bash
php artisan key:generate
php artisan jwt:secret
```

### 3. Cấu hình database

Ví dụ MySQL:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=clothes_shop
DB_USERNAME=root
DB_PASSWORD=
```

Nếu dùng SQLite:

```env
DB_CONNECTION=sqlite
DB_DATABASE=/duong-dan-tuyet-doi/database/database.sqlite
```

Tạo file SQLite nếu chưa có:

```bash
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
```

### 4. Cấu hình tài khoản admin

```env
ADMIN_NAME=Administrator
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=change-this-password
ADMIN_PHONE=0900000000
```

Seeder sẽ bỏ qua bước tạo admin nếu `ADMIN_EMAIL` hoặc `ADMIN_PASSWORD` để trống.

### 5. Chạy migration và seeder

```bash
php artisan migrate --seed
```

### 6. Build frontend assets

Development:

```bash
npm run dev
```

Production:

```bash
npm run build
```

## Cấu hình dịch vụ ngoài

### GHN

```env
GHN_BASE_URL=https://dev-online-gateway.ghn.vn
GHN_TOKEN=
GHN_SHOP_ID=
GHN_WEBHOOK_TOKEN=replace-with-a-random-secret
GHN_VERIFY_SSL=true
GHN_DEFAULT_SERVICE_TYPE_ID=2
GHN_DEFAULT_WEIGHT=300
GHN_DEFAULT_LENGTH=25
GHN_DEFAULT_WIDTH=20
GHN_DEFAULT_HEIGHT=3
```

Webhook cập nhật trạng thái đơn:

```text
POST /api/ghn/webhook/order-status
```

GHN cần gửi mã vận đơn và token webhook. Token có thể được gửi qua `X-GHN-Webhook-Token`, `X-Webhook-Token`, Bearer token hoặc trường `token` trong body.

### VNPAY

```env
VNPAY_PAYMENT_URL=https://sandbox.vnpayment.vn/paymentv2/vpcpay.html
VNPAY_TMN_CODE=
VNPAY_HASH_SECRET=
VNPAY_RETURN_URL=http://localhost:8000/api/vnpay/return
VNPAY_VERSION=2.1.0
VNPAY_COMMAND=pay
VNPAY_CURRENCY=VND
VNPAY_LOCALE=vn
VNPAY_ORDER_TYPE=other
VNPAY_EXPIRE_MINUTES=15
```

Không đưa `VNPAY_HASH_SECRET` vào frontend hoặc commit lên Git.

### Cloudinary

```env
CLOUDINARY_CLOUD_NAME=
CLOUDINARY_API_KEY=
CLOUDINARY_API_SECRET=
CLOUDINARY_SECURE=true
```

Các giá trị Cloudinary mẫu trong `.env.example` chỉ là placeholder và không dùng được cho upload thật.

### Mail và frontend URL

Để gửi email đặt lại mật khẩu, cấu hình mail driver phù hợp và URL frontend:

```env
FRONTEND_URL=http://localhost:5173
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=no-reply@example.com
MAIL_FROM_NAME="${APP_NAME}"
```

## Chạy dự án

Cách nhanh nhất:

```bash
composer run dev
```

Lệnh trên chạy Laravel server, queue listener, log viewer và Vite. Scheduler cần chạy ở terminal riêng:

```bash
php artisan schedule:work
```

Hoặc chạy từng tiến trình:

```bash
php artisan serve
php artisan queue:work
php artisan schedule:work
npm run dev
```

API mặc định:

```text
http://localhost:8000/api
```

Health check:

```text
GET http://localhost:8000/up
```

## Scheduler

Scheduler hiện chạy mỗi phút:

- `promotions:sync`: đồng bộ giá và trạng thái khuyến mãi.
- `app:sync-order`: hủy đơn VNPAY chưa thanh toán sau thời gian chờ.

Trong production, cấu hình cron gọi scheduler của Laravel:

```cron
* * * * * cd /path/to/clothes_shop && php artisan schedule:run >> /dev/null 2>&1
```

## Xác thực API

Các API được bảo vệ sử dụng JWT:

```http
Authorization: Bearer <access_token>
Accept: application/json
```

Nhóm endpoint chính:

- `/api/auth/*`: xác thực và đặt lại mật khẩu.
- `/api/products`, `/api/categories`, `/api/attributes`: catalog sản phẩm.
- `/api/cart/items`: giỏ hàng.
- `/api/order`: đơn hàng khách hàng.
- `/api/vnpay/*`: thanh toán VNPAY.
- `/api/ghn/*`: địa chỉ, phí vận chuyển và webhook GHN.
- `/api/admin/*`: đơn hàng, tồn kho và báo cáo quản trị.
- `/api/voucher`, `/api/promotion`: voucher và khuyến mãi.

Xem danh sách route đầy đủ:

```bash
php artisan route:list --except-vendor
```

## Swagger/OpenAPI

Tạo lại tài liệu sau khi sửa annotation:

```bash
php artisan l5-swagger:generate
```

Khởi động ứng dụng và truy cập:

```text
http://localhost:8000/swagger
```

OpenAPI YAML:

```text
http://localhost:8000/api-docs.yaml
```

Trong Swagger UI, dùng nút **Authorize** và nhập JWT Bearer token để gọi các endpoint yêu cầu đăng nhập.

## Kiểm thử và format code

Chạy toàn bộ test:

```bash
composer test
```

Chạy một nhóm test:

```bash
php artisan test tests/Feature/OrderStatusWorkflowTest.php
php artisan test tests/Feature/ProductControllerTest.php
```

Format code:

```bash
php vendor/bin/pint
```

Test sử dụng SQLite in-memory theo cấu hình trong `phpunit.xml`.

## Cấu trúc thư mục chính

```text
app/
├── Console/Commands/       Artisan commands
├── Enums/                  Enum nghiệp vụ
├── Http/Controllers/       API controllers và OpenAPI attributes
├── Http/Middleware/        Middleware phân quyền
├── Http/Services/          Nghiệp vụ order, GHN và VNPAY
└── Models/                 Eloquent models

database/
├── factories/
├── migrations/
└── seeders/

routes/
├── api.php
├── console.php
└── web.php

storage/api-docs/            Tài liệu OpenAPI đã generate
tests/                       Unit và feature tests
```

## Lưu ý bảo mật

- Không commit file `.env`.
- Không ghi log hoặc trả về API các secret của JWT, GHN, VNPAY và Cloudinary.
- Dùng HTTPS cho callback VNPAY và webhook GHN ở production.
- Đặt `APP_DEBUG=false` và thay toàn bộ credential sandbox trước khi deploy.
- Sử dụng webhook token đủ dài, ngẫu nhiên và khác credential của GHN.
