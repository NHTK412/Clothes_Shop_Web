<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\AttributeType;
use App\Models\AttributeValue;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Favorite;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\RefundRequest;
use App\Models\Review;
use App\Models\ReviewImage;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Model::unguard();

        $customers = User::query()
            ->where('role', 'ROLE_CUSTOMER')
            ->get();

        if ($customers->count() < 3) {
            $customers = collect([
                User::factory()->create([
                    'name' => 'Nguyễn Văn An',
                    'email' => 'an@example.com',
                    'phone' => '0901111111',
                    'password' => bcrypt('password123'),
                    'role' => 'ROLE_CUSTOMER',
                    'status' => 'ACTIVE',
                ]),
                User::factory()->create([
                    'name' => 'Trần Thị Bình',
                    'email' => 'binh@example.com',
                    'phone' => '0902222222',
                    'password' => bcrypt('password123'),
                    'role' => 'ROLE_CUSTOMER',
                    'status' => 'ACTIVE',
                ]),
                User::factory()->create([
                    'name' => 'Lê Minh Cường',
                    'email' => 'cuong@example.com',
                    'phone' => '0903333333',
                    'password' => bcrypt('password123'),
                    'role' => 'ROLE_CUSTOMER',
                    'status' => 'ACTIVE',
                ]),
            ]);
        }

        $this->seedCategories();
        $products = $this->seedProducts();
        $variants = $this->seedVariants($products);
        $this->seedAttributesAndValues($variants);
        $this->seedAddresses($customers);
        $this->seedVouchers();
        $this->seedCarts($customers, $variants);
        $this->seedOrdersAndPayments($customers, $variants);
        $this->seedReviews($customers, $products, $variants);
        $this->seedFavorites($customers, $products);
        $this->seedRefundRequests();
    }

    private function seedCategories(): void
    {
        $categories = [
            ['name' => 'Nam', 'image' => 'categories/men.jpg'],
            ['name' => 'Nữ', 'image' => 'categories/women.jpg'],
            ['name' => 'Phụ kiện', 'image' => 'categories/accessories.jpg'],
        ];

        foreach ($categories as $data) {
            $attributes = ['name' => $data['name']];
            if (Schema::hasColumn('categories', 'image')) {
                $attributes['image'] = $data['image'];
            }

            Category::firstOrCreate(
                ['name' => $data['name']],
                $attributes
            );
        }
    }

    private function seedProducts(): array
    {
        $productsData = [
            ['name' => 'Áo thun basic', 'description' => 'Áo thun cotton thoáng mát', 'price' => 199000, 'discount_price' => 159000, 'image' => 'products/basic-shirt.jpg', 'category' => 'Nam'],
            ['name' => 'Áo khoác denim', 'description' => 'Áo khoác denim thời trang', 'price' => 399000, 'discount_price' => 349000, 'image' => 'products/denim-jacket.jpg', 'category' => 'Nam'],
            ['name' => 'Quần jean nữ', 'description' => 'Quần jean nữ co giãn', 'price' => 299000, 'discount_price' => 249000, 'image' => 'products/women-jeans.jpg', 'category' => 'Nữ'],
            ['name' => 'Váy maxi', 'description' => 'Váy maxi nhẹ, sang trọng', 'price' => 429000, 'discount_price' => 379000, 'image' => 'products/maxi-dress.jpg', 'category' => 'Nữ'],
            ['name' => 'Túi xách nhỏ', 'description' => 'Túi xách đi học', 'price' => 259000, 'discount_price' => 219000, 'image' => 'products/small-bag.jpg', 'category' => 'Phụ kiện'],
            ['name' => 'Mũ lưỡi chai', 'description' => 'Mũ thời trang', 'price' => 149000, 'discount_price' => 129000, 'image' => 'products/cap.jpg', 'category' => 'Phụ kiện'],
        ];

        $products = [];
        foreach ($productsData as $data) {
            $product = Product::firstOrCreate(
                ['name' => $data['name']],
                [
                    'description' => $data['description'],
                    'price' => $data['price'],
                    'discount_price' => $data['discount_price'],
                    'image' => $data['image'],
                ]
            );

            $category = Category::where('name', $data['category'])->first();
            if ($category) {
                $product->categories()->syncWithoutDetaching([$category->id]);
            }

            $products[] = $product;
        }

        return $products;
    }

    private function seedVariants(array $products): array
    {
        $variants = [];
        foreach ($products as $product) {
            $variantCount = $product->name === 'Áo thun basic' || $product->name === 'Áo khoác denim' ? 2 : 1;
            for ($i = 1; $i <= $variantCount; $i++) {
                $variant = ProductVariant::firstOrCreate(
                    ['sku' => strtoupper(Str::slug($product->name)) . '-' . $i],
                    [
                        'price' => $product->price + ($i * 10000),
                        'discount_price' => $product->discount_price + ($i * 5000),
                        'stock' => 50 + $i * 10,
                        'image' => $product->image,
                        'product_id' => $product->id,
                    ]
                );
                $variants[] = $variant;
            }
        }

        return $variants;
    }

    private function seedAttributesAndValues(array $variants): void
    {
        $colorType = AttributeType::firstOrCreate(
            ['name' => 'color'],
            ['display_name' => 'Màu sắc']
        );
        $sizeType = AttributeType::firstOrCreate(
            ['name' => 'size'],
            ['display_name' => 'Kích cỡ']
        );

        $colors = [
            ['value' => 'black', 'display_value' => 'Đen'],
            ['value' => 'white', 'display_value' => 'Trắng'],
            ['value' => 'blue', 'display_value' => 'Xanh'],
        ];
        $sizes = [
            ['value' => 's', 'display_value' => 'S'],
            ['value' => 'm', 'display_value' => 'M'],
            ['value' => 'l', 'display_value' => 'L'],
        ];

        $colorValues = [];
        foreach ($colors as $data) {
            $colorValues[] = AttributeValue::firstOrCreate(
                ['value' => $data['value'], 'attribute_type_id' => $colorType->id],
                ['display_value' => $data['display_value']]
            );
        }

        $sizeValues = [];
        foreach ($sizes as $data) {
            $sizeValues[] = AttributeValue::firstOrCreate(
                ['value' => $data['value'], 'attribute_type_id' => $sizeType->id],
                ['display_value' => $data['display_value']]
            );
        }

        foreach ($variants as $index => $variant) {
            $variant->attributeValues()->syncWithoutDetaching([
                $colorValues[$index % count($colorValues)]->id,
                $sizeValues[$index % count($sizeValues)]->id,
            ]);
        }
    }

    private function seedAddresses($customers): void
    {
        $addresses = [
            ['full_name' => 'Nguyễn Văn An', 'phone' => '0901111111', 'specific_address' => '123 Nguyễn Văn Cừ', 'ward_code' => '20314', 'ward_name' => 'Phường 1', 'province_id' => 79, 'province_name' => 'Thành phố Hồ Chí Minh', 'is_default' => true],
            ['full_name' => 'Trần Thị Bình', 'phone' => '0902222222', 'specific_address' => '456 Lê Lợi', 'ward_code' => '20315', 'ward_name' => 'Phường 2', 'province_id' => 79, 'province_name' => 'Thành phố Hồ Chí Minh', 'is_default' => true],
            ['full_name' => 'Lê Minh Cường', 'phone' => '0903333333', 'specific_address' => '789 Hai Bà Trưng', 'ward_code' => '20316', 'ward_name' => 'Phường 3', 'province_id' => 79, 'province_name' => 'Thành phố Hồ Chí Minh', 'is_default' => true],
        ];

        foreach ($customers as $index => $customer) {
            if (! $customer->addresses()->exists()) {
                $data = $addresses[$index % count($addresses)];
                Address::create(array_merge([
                    'user_id' => $customer->id,
                ], $data));
            }
        }
    }

    private function seedVouchers(): void
    {
        $vouchers = [
            ['code' => 'WELCOME10', 'description' => 'Giảm 10% đơn hàng', 'discount_amount' => 10, 'max_discount_amount' => 50000, 'discount_type' => 'ORDER', 'is_active' => true, 'usage_limit' => 100, 'expiry_date' => now()->addMonth()->toDateString()],
            ['code' => 'SHIPFREE', 'description' => 'Miễn phí ship', 'discount_amount' => 100, 'max_discount_amount' => 30000, 'discount_type' => 'SHIPPING', 'is_active' => true, 'usage_limit' => 50, 'expiry_date' => now()->addMonths(2)->toDateString()],
            ['code' => 'SPRING20', 'description' => 'Giảm 20% cho đơn hàng đầu tiên', 'discount_amount' => 20, 'max_discount_amount' => 100000, 'discount_type' => 'ORDER', 'is_active' => true, 'usage_limit' => 30, 'expiry_date' => now()->addMonths(3)->toDateString()],
        ];

        foreach ($vouchers as $data) {
            Voucher::firstOrCreate(['code' => $data['code']], $data);
        }
    }

    private function seedCarts($customers, array $variants): void
    {
        foreach ($customers as $customer) {
            $cart = Cart::firstOrCreate(['user_id' => $customer->id]);
            $variant = $variants[array_rand($variants)];
            CartItem::firstOrCreate(
                ['cart_id' => $cart->id, 'product_variant_id' => $variant->id],
                ['quantity' => 1 + rand(1, 2)]
            );
        }
    }

    private function seedOrdersAndPayments($customers, array $variants): void
    {
        $statusMap = [
            'PENDING_PAYMENT' => 'pending',
            'CONFIRMED' => 'processing',
            'SHIPPING' => 'processing',
            'COMPLETED' => 'completed',
            'CANCELLED' => 'cancelled',
            'RETURNED' => 'returned',
        ];

        $ordersPayload = [
            ['user' => $customers[0], 'status' => 'COMPLETED', 'ghn_order_code' => 'GHN-100001', 'quantity' => 2],
            ['user' => $customers[1], 'status' => 'SHIPPING', 'ghn_order_code' => 'GHN-100002', 'quantity' => 1],
            ['user' => $customers[2], 'status' => 'PENDING_PAYMENT', 'ghn_order_code' => 'GHN-100003', 'quantity' => 3],
            ['user' => $customers[0], 'status' => 'CONFIRMED', 'ghn_order_code' => 'GHN-100004', 'quantity' => 1],
            ['user' => $customers[1], 'status' => 'CANCELLED', 'ghn_order_code' => 'GHN-100005', 'quantity' => 4],
            ['user' => $customers[2], 'status' => 'RETURNED', 'ghn_order_code' => 'GHN-100006', 'quantity' => 2],
        ];

        foreach ($ordersPayload as $payload) {
            $variant = $variants[array_rand($variants)];
            $unitPrice = (float) ($variant->discount_price ?: $variant->price);
            $quantity = (int) $payload['quantity'];
            $subtotal = $unitPrice * $quantity;
            $shippingFee = 30000;
            $discount = 5000;
            $finalPrice = $subtotal + $shippingFee - $discount;

            $orderAttributes = [
                'user_id' => $payload['user']->id,
                'total_price' => $subtotal,
                'discount_price' => $discount,
                'final_price' => $finalPrice,
                'status' => $statusMap[$payload['status']] ?? 'pending',
            ];

            if (Schema::hasColumn('orders', 'ship_price')) {
                $orderAttributes['ship_price'] = $shippingFee;
            }

            if (Schema::hasColumn('orders', 'discount_ship_price')) {
                $orderAttributes['discount_ship_price'] = 0;
            }

            if (Schema::hasColumn('orders', 'ghn_order_code')) {
                $orderAttributes['ghn_order_code'] = $payload['ghn_order_code'];
            }

            $order = Order::firstOrCreate(
                [
                    'user_id' => $payload['user']->id,
                    'status' => $orderAttributes['status'],
                    'final_price' => $finalPrice,
                ],
                $orderAttributes
            );

            $detail = OrderDetail::firstOrCreate(
                [
                    'order_id' => $order->id,
                    'product_variant_id' => $variant->id,
                ],
                [
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'unit_discount_price' => 0,
                ]
            );

            if (Schema::hasTable('payments')) {
                Payment::firstOrCreate(
                    ['order_id' => $order->id],
                    [
                        'method' => $order->id % 2 === 0 ? 'VNPAY' : 'COD',
                        'status' => 'completed',
                        'transaction_id' => 'TXN-' . $order->id,
                        'payment_details' => ['gateway' => 'mock'],
                    ]
                );
            }

            $detail->review_id = null;
        }
    }

    private function seedReviews($customers, array $products, array $variants): void
    {
        $orders = Order::query()->where('status', 'completed')->get();
        foreach ($orders as $index => $order) {
            if ($order->orderDetails()->exists()) {
                $detail = $order->orderDetails()->first();
                $user = $customers->firstWhere('id', $order->user_id);
                $product = $products[$index % count($products)];

                $review = Review::firstOrCreate(
                    ['order_detail_id' => $detail->id],
                    [
                        'user_id' => $user?->id ?? $customers->first()->id,
                        'product_id' => $product->id,
                        'rating' => 4 + ($index % 2),
                        'comment' => 'Sản phẩm tốt, giao hàng nhanh.',
                    ]
                );

                ReviewImage::firstOrCreate(
                    ['review_id' => $review->id],
                    ['image_path' => 'reviews/review-' . $review->id . '.jpg']
                );
            }
        }
    }

    private function seedFavorites($customers, array $products): void
    {
        foreach ($customers as $customer) {
            $product = $products[array_rand($products)];
            Favorite::firstOrCreate(
                ['user_id' => $customer->id, 'product_id' => $product->id]
            );
        }
    }

    private function seedRefundRequests(): void
    {
        $orders = Order::query()->where('status', 'cancelled')->get();
        foreach ($orders as $order) {
            RefundRequest::firstOrCreate(
                ['order_id' => $order->id],
                [
                    'user_id' => $order->user_id,
                    'reason' => 'Đổi size',
                    'status' => 'pending',
                    'amount' => $order->final_price,
                ]
            );
        }
    }
}
