# API trả hàng và hoàn tiền

Base URL: `/api`

Tất cả endpoint bên dưới dùng `Authorization: Bearer <access_token>`. Các endpoint
`/admin/*` chỉ dành cho tài khoản `ROLE_ADMIN`.

`GET /order` và `GET /order/{order_id}` trả thêm `can_request_return` và
`return_request`. FE hiện nút **Yêu cầu trả hàng** khi
`can_request_return === true`. Sau khi khách gửi yêu cầu, cờ này thành `false`,
`orders.status` là `RETURNED` và `return_request.status` là `pending`.

## Trạng thái

Yêu cầu trả hàng:

- `pending`: đang chờ shop xử lý.
- `approved`: shop đã duyệt, vận đơn GHN chiều về đã được tạo và refund đã được sinh.
- `rejected`: shop từ chối.
- `cancelled`: khách đã hủy yêu cầu trước khi shop xử lý.

Hoàn tiền:

- `pending`: đang chờ shop hoàn tiền.
- `approved`: đã hoàn tiền. Payment của đơn hàng được chuyển thành `REFUNDED`.
- `rejected`: không thực hiện hoàn tiền; lý do nên ghi trong `note`.

API refund cố ý **không trả trường số tiền**. Số tiền/phương án hoàn được shop và
khách trao đổi riêng, sau đó ghi nội dung thống nhất vào `note`.

## API khách hàng

### Tạo yêu cầu trả hàng

`POST /return-requests`

Chỉ đơn hàng `COMPLETED` của chính khách đang đăng nhập mới được tạo yêu cầu.
Mỗi đơn chỉ có một yêu cầu trả hàng.

```json
{
  "order_id": 12,
  "reason": "Sản phẩm không đúng kích thước đã đặt"
}
```

Response `201`:

```json
{
  "status": 201,
  "success": true,
  "message": null,
  "data": {
    "id": 7,
    "order_id": 12,
    "reason": "Sản phẩm không đúng kích thước đã đặt",
    "status": "pending",
    "note": null,
    "ghn_order_code": null,
    "expected_delivery_at": null,
    "refund_id": null,
    "refund_status": null,
    "created_at": "2026-07-01T08:00:00.000000Z",
    "updated_at": "2026-07-01T08:00:00.000000Z",
    "order": {
      "id": 12,
      "status": "COMPLETED",
      "full_name": "Nguyễn Văn Test GHN",
      "phone": "0777066412",
      "payment_method": "VNPAY",
      "payment_status": "PAID"
    }
  }
}
```

### Danh sách yêu cầu trả hàng của khách

`GET /return-requests?status=pending&page=1&per_page=15`

`status` không bắt buộc. Giá trị hợp lệ: `pending`, `approved`, `rejected`,
`cancelled`. `per_page` tối đa `100`.

Response dùng cấu trúc:

```json
{
  "status": 200,
  "success": true,
  "message": null,
  "data": {
    "items": [],
    "pagination": {
      "current_page": 1,
      "per_page": 15,
      "total": 0,
      "last_page": 1
    }
  }
}
```

### Chi tiết yêu cầu trả hàng

`GET /return-requests/{return_request_id}`

### Khách hủy yêu cầu

`PATCH /return-requests/{return_request_id}/cancel`

Không cần body. Chỉ hủy được khi trạng thái hiện tại là `pending`.

### Danh sách hoàn tiền của khách

`GET /refunds?status=pending&order_id=12&page=1&per_page=15`

`status` và `order_id` đều không bắt buộc. Item refund:

```json
{
  "id": 5,
  "order_id": 12,
  "return_request_id": 7,
  "reason": "Hoàn tiền cho yêu cầu trả hàng đã được duyệt",
  "status": "pending",
  "note": "Đã thống nhất phương án hoàn tiền với khách",
  "transfer_image": null,
  "completed_at": null,
  "created_at": "2026-07-01T08:10:00.000000Z",
  "updated_at": "2026-07-01T08:10:00.000000Z",
  "order": {
    "id": 12,
    "status": "COMPLETED",
    "payment_method": "VNPAY",
    "payment_status": "PAID"
  }
}
```

## API quản trị

### Danh sách yêu cầu trả hàng

`GET /admin/return-requests?status=pending&order_id=12&page=1&per_page=15`

Response giống danh sách phía khách, bổ sung `customer`, `ghn_fee`,
`approved_at`, `rejected_at`, `cancelled_at`.

Trong `customer`, `name` và `phone` lấy từ snapshot địa chỉ nhận hàng trên
order; `email` lấy từ user.

### Chi tiết yêu cầu trả hàng

`GET /admin/return-requests/{return_request_id}`

### Duyệt hoặc từ chối trả hàng

`PATCH /admin/return-requests/{return_request_id}/status`

Duyệt:

```json
{
  "status": "approved",
  "note": "Đã trao đổi và thống nhất phương án hoàn tiền với khách"
}
```

Khi duyệt, backend gọi GHN tạo vận đơn lấy hàng từ địa chỉ giao của khách về shop.
Chỉ sau khi GHN trả về thành công, yêu cầu mới chuyển sang `approved` và refund
`pending` mới được tạo. Nếu GHN lỗi, API trả `422` và yêu cầu vẫn là `pending`.
Gọi lại cùng trạng thái không tạo thêm vận đơn/refund.

Từ chối:

```json
{
  "status": "rejected",
  "note": "Quá thời hạn tiếp nhận trả hàng"
}
```

### Danh sách hoàn tiền

`GET /admin/refunds?status=pending&order_id=12&page=1&per_page=15`

Response giống danh sách phía khách và bổ sung `customer`.

Trong `customer`, `name` và `phone` được lấy từ thông tin nhận hàng đã lưu trên
order (`orders.full_name`, `orders.phone`); chỉ `email` lấy từ tài khoản user.
Điều này đảm bảo thông tin hoàn tiền đúng với người nhận của đơn tại thời điểm
đặt hàng, kể cả khi profile user không có số điện thoại.

### Cập nhật note và ảnh chuyển khoản

`PATCH /admin/refunds/{refund_id}`

Có thể gửi một hoặc cả hai trường. Gửi `null` để xóa giá trị cũ.

```json
{
  "note": "Đã chuyển khoản theo thông tin khách cung cấp",
  "transfer_image": "https://res.cloudinary.com/.../refund-proof.jpg"
}
```

FE có thể upload ảnh trước qua `POST /api/upload` với form-data `image`, sau đó
lấy `data.image_url` gán vào `transfer_image`.

### Thay đổi trạng thái hoàn tiền

`PATCH /admin/refunds/{refund_id}/status`

```json
{
  "status": "approved"
}
```

Admin chỉ chuyển refund `pending` sang `approved` hoặc `rejected`. Khi chuyển
sang `approved`, backend ghi `completed_at` và cập nhật payment thành `REFUNDED`.

## Đồng bộ trạng thái đơn hàng

Khi khách tạo yêu cầu trả hàng, `orders.status` chuyển từ `COMPLETED` sang
`RETURNED`. Nếu khách hủy yêu cầu khi còn `pending`, hoặc admin từ chối, trạng
thái đơn được chuyển về `COMPLETED`. Khi admin duyệt, đơn tiếp tục giữ
`RETURNED`.

FE nên hiển thị nhãn "Trả hàng" cho đơn `RETURNED` và dùng trạng thái chi tiết
trong `return_request.status` để phân biệt đang chờ duyệt, đã duyệt hay bị từ
chối.

## GHN webhook

Endpoint hiện tại:

`POST /ghn/webhook/order-status`

Webhook phải gửi token đúng cấu hình `GHN_WEBHOOK_TOKEN`. Backend luôn gọi API
chi tiết GHN để xác minh trạng thái chính thức. Khi trạng thái GHN thuộc nhóm trả
hàng (`waiting_to_return`, `return`, `return_transporting`, `return_sorting`,
`returning`, `return_fail`, `returned`):

1. Order chuyển sang `RETURNED` nếu state transition hợp lệ.
2. Backend tạo refund `pending`.
3. Webhook lặp lại vẫn chỉ có một refund cho đơn đó.

## Cấu hình backend

```dotenv
GHN_TOKEN=
GHN_SHOP_ID=
GHN_WEBHOOK_TOKEN=
GHN_RETURN_SHOP_NAME="Clothes Shop"
GHN_RETURN_SHOP_PHONE=0366408263
GHN_RETURN_SHOP_ADDRESS="Đường NA12"
GHN_RETURN_SHOP_WARD_CODE=1003601
GHN_RETURN_SHOP_PROVINCE_NAME="Hồ Chí Minh"
```

Payload GHN chiều trả dùng `payment_type_id=2`, `cod_amount=0`,
`required_note=KHONGCHOXEMHANG`; kích thước và cân nặng lấy từ cấu hình GHN mặc
định của dự án.

## Mã lỗi FE cần xử lý

- `401`: chưa đăng nhập/webhook token sai.
- `403`: tài khoản không phải admin.
- `404`: resource không tồn tại hoặc không thuộc khách hiện tại.
- `422`: validation/state transition sai hoặc GHN không tạo được vận đơn.
- `500`: thiếu cấu hình server.
- `502`: không kết nối được dịch vụ ngoài ở các luồng hiện hữu.
