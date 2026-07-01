# API quản lý banner

Base URL: `/api`

Một banner gồm:

| Trường | Kiểu | Mô tả |
|---|---|---|
| `id` | integer | ID banner |
| `label` | string | Nhãn nhỏ, ví dụ `Bộ sưu tập mới` |
| `title` | string | Tiêu đề chính |
| `description` | string | Nội dung mô tả |
| `image_url` | string | URL ảnh banner |
| `created_at` | datetime | Thời điểm tạo |
| `updated_at` | datetime | Thời điểm cập nhật |

## 1. API public dành cho giao diện khách

### Danh sách banner

```http
GET /banners
```

Không yêu cầu đăng nhập. Banner mới nhất nằm đầu danh sách.

Response `200`:

```json
{
  "status": 200,
  "success": true,
  "message": null,
  "data": {
    "items": [
      {
        "id": 1,
        "label": "Bộ sưu tập mới",
        "title": "Thanh lịch trong từng khoảnh khắc",
        "description": "Những thiết kế tối giản, hiện đại giúp bạn tự tin từ công sở đến những cuộc hẹn cuối tuần.",
        "image_url": "https://cdn.example.com/banners/new-collection.jpg",
        "created_at": "2026-07-01T08:00:00.000000Z",
        "updated_at": "2026-07-01T08:00:00.000000Z"
      }
    ],
    "pagination": null
  }
}
```

Nếu trang chủ chỉ hiển thị một banner, FE lấy `data.items[0]`. Cần kiểm tra mảng
rỗng trước khi render.

### Chi tiết banner

```http
GET /banners/{banner_id}
```

Không yêu cầu đăng nhập. Response trả banner trực tiếp trong `data`.

## 2. API quản trị

Các endpoint bên dưới yêu cầu:

```http
Authorization: Bearer <admin_access_token>
Content-Type: application/json
```

### Tạo banner

```http
POST /admin/banners
```

```json
{
  "label": "Bộ sưu tập mới",
  "title": "Thanh lịch trong từng khoảnh khắc",
  "description": "Những thiết kế tối giản, hiện đại giúp bạn tự tin từ công sở đến những cuộc hẹn cuối tuần.",
  "image_url": "https://cdn.example.com/banners/new-collection.jpg"
}
```

Tất cả bốn trường đều bắt buộc khi tạo. `image_url` phải là URL hợp lệ.
Response thành công có HTTP status `201`.

### Cập nhật banner

```http
PATCH /admin/banners/{banner_id}
```

Hoặc:

```http
PUT /admin/banners/{banner_id}
```

Có thể chỉ gửi các trường cần thay đổi:

```json
{
  "title": "Thanh lịch trong mọi khoảnh khắc",
  "image_url": "https://cdn.example.com/banners/new-collection-v2.jpg"
}
```

Response `200` trả banner sau khi cập nhật trong `data`.

### Xóa banner

```http
DELETE /admin/banners/{banner_id}
```

Response:

```json
{
  "status": 200,
  "success": true,
  "message": "Đã xóa banner thành công.",
  "data": null
}
```

## 3. Quy trình upload ảnh từ FE

Có thể sử dụng endpoint upload hiện tại:

```http
POST /upload
Authorization: Bearer <admin_access_token>
Content-Type: multipart/form-data
```

Form-data:

```text
image: <file ảnh>
```

Response:

```json
{
  "status": 200,
  "success": true,
  "data": {
    "image_url": "https://res.cloudinary.com/.../banner.jpg",
    "public_id": "clothes_shop/products/banner"
  }
}
```

FE lấy `data.image_url`, sau đó gửi URL này vào `image_url` khi tạo hoặc cập nhật
banner.

Flow đề xuất:

1. Admin chọn ảnh.
2. FE gọi `POST /api/upload`.
3. Lấy `data.image_url`.
4. Gọi `POST /api/admin/banners` hoặc `PATCH /api/admin/banners/{id}`.
5. Tải lại `GET /api/banners`.

## 4. Validation và mã lỗi

- `401`: chưa đăng nhập.
- `403`: tài khoản không có quyền admin.
- `404`: banner không tồn tại.
- `422`: thiếu dữ liệu bắt buộc, chuỗi quá dài hoặc `image_url` không hợp lệ.

Giới hạn:

- `label`: tối đa 255 ký tự.
- `title`: tối đa 500 ký tự.
- `description`: tối đa 5000 ký tự.
- `image_url`: tối đa 2048 ký tự.
