# API hiển thị đánh giá sản phẩm

Base URL: `/api`

Hai endpoint đọc review là public, khách chưa đăng nhập vẫn sử dụng được.

## 1. Danh sách đánh giá

```http
GET /products/{product_id}/reviews
```

### Query parameters

| Tham số | Kiểu | Bắt buộc | Mô tả |
|---|---:|:---:|---|
| `rating` | integer | Không | Lọc chính xác từ 1 đến 5 sao |
| `has_images` | boolean | Không | `1` chỉ lấy review có ảnh, `0` chỉ lấy review không có ảnh |
| `sort` | string | Không | `newest`, `oldest`, `highest_rating`, `lowest_rating`; mặc định `newest` |
| `page` | integer | Không | Trang hiện tại, mặc định `1` |
| `per_page` | integer | Không | Số review mỗi trang, mặc định `10`, tối đa `100` |

Ví dụ:

```http
GET /api/products/25/reviews?rating=5&has_images=1&sort=newest&page=1&per_page=10
```

Response `200`:

```json
{
  "status": 200,
  "success": true,
  "message": null,
  "data": {
    "items": [
      {
        "id": 31,
        "rating": 5,
        "comment": "Áo đẹp, đúng kích thước",
        "customer": {
          "id": 8,
          "name": "Nguyễn Văn A",
          "avatar": "https://cdn.example.com/avatar.jpg"
        },
        "variant": {
          "id": 42,
          "sku": "SHIRT-BLACK-M",
          "attributes": [
            {
              "type": "color",
              "type_label": "Màu sắc",
              "value": "black",
              "value_label": "Đen"
            },
            {
              "type": "size",
              "type_label": "Kích thước",
              "value": "M",
              "value_label": "M"
            }
          ]
        },
        "images": [
          {
            "id": 12,
            "url": "https://cdn.example.com/reviews/review-31.jpg"
          }
        ],
        "created_at": "2026-07-01T08:00:00.000000Z",
        "updated_at": "2026-07-01T08:00:00.000000Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 10,
      "total": 26,
      "last_page": 3
    }
  }
}
```

FE dùng `data.items` để render danh sách. Khi người dùng đổi bộ lọc sao hoặc bộ
lọc ảnh, đặt lại `page=1`.

## 2. Tổng hợp số sao

```http
GET /products/{product_id}/reviews/summary
```

Ví dụ:

```http
GET /api/products/25/reviews/summary
```

Response `200`:

```json
{
  "status": 200,
  "success": true,
  "message": null,
  "data": {
    "product_id": 25,
    "average_rating": 4.25,
    "total_reviews": 20,
    "distribution": [
      {
        "rating": 5,
        "count": 12,
        "percentage": 60
      },
      {
        "rating": 4,
        "count": 3,
        "percentage": 15
      },
      {
        "rating": 3,
        "count": 3,
        "percentage": 15
      },
      {
        "rating": 2,
        "count": 1,
        "percentage": 5
      },
      {
        "rating": 1,
        "count": 1,
        "percentage": 5
      }
    ]
  }
}
```

`average_rating` được làm tròn tối đa 2 chữ số thập phân. `distribution` luôn có
đủ 5 phần tử theo thứ tự từ 5 sao xuống 1 sao.

Nếu sản phẩm chưa có đánh giá:

```json
{
  "product_id": 25,
  "average_rating": 0,
  "total_reviews": 0,
  "distribution": [
    {"rating": 5, "count": 0, "percentage": 0},
    {"rating": 4, "count": 0, "percentage": 0},
    {"rating": 3, "count": 0, "percentage": 0},
    {"rating": 2, "count": 0, "percentage": 0},
    {"rating": 1, "count": 0, "percentage": 0}
  ]
}
```

## 3. Cách tích hợp ở trang chi tiết sản phẩm

Khi mở trang sản phẩm, FE nên gọi song song:

```text
GET /api/products/{product_id}
GET /api/products/{product_id}/reviews/summary
GET /api/products/{product_id}/reviews?page=1&per_page=10
```

- Dùng `summary.average_rating` để hiển thị số sao trung bình.
- Dùng `summary.total_reviews` để hiển thị tổng số lượt đánh giá.
- Dùng `summary.distribution` cho thanh tỷ lệ 5–1 sao.
- Dùng endpoint danh sách khi phân trang, lọc sao hoặc lọc review có ảnh.
- Không cần tải lại summary khi chỉ đổi trang danh sách.

## 4. Gửi đánh giá sau khi mua hàng

Endpoint đã có:

```http
POST /order/{order_id}/{order_detail_id}/review
Authorization: Bearer <access_token>
Content-Type: application/json
```

```json
{
  "rating": 5,
  "comment": "Sản phẩm đẹp",
  "imagePaths": [
    "https://cdn.example.com/reviews/image-1.jpg"
  ]
}
```

Chỉ đơn `COMPLETED` thuộc khách đang đăng nhập mới được đánh giá và mỗi
`order_detail` chỉ được đánh giá một lần. `rating` từ 1–5; tối đa 5 ảnh.

Sau khi tạo review thành công, FE nên gọi lại endpoint summary và tải lại trang
đầu danh sách review.

## 5. Mã lỗi

- `404`: không tìm thấy sản phẩm, đơn hàng hoặc chi tiết đơn hàng.
- `422`: query không hợp lệ, rating ngoài 1–5, đơn chưa hoàn thành hoặc sản phẩm
  đã được đánh giá.
