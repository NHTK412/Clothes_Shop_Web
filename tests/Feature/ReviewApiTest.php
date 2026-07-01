<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\AttributeType;
use App\Models\AttributeValue;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_list_and_filter_product_reviews(): void
    {
        [$product, $variant] = $this->createProductWithVariant();
        $color = AttributeType::create(['name' => 'color', 'display_name' => 'Màu sắc']);
        $black = AttributeValue::create([
            'attribute_type_id' => $color->id,
            'value' => 'black',
            'display_value' => 'Đen',
        ]);
        $variant->attributeValues()->attach($black->id);

        $reviewWithImage = $this->createReview($product, $variant, 5, 'Rất đẹp');
        $reviewWithImage->images()->create(['image_path' => 'https://cdn.example.com/review.jpg']);
        $this->createReview($product, $variant, 5, 'Chất lượng tốt');
        $this->createReview($product, $variant, 4, 'Khá ổn');
        $this->createReview($product, $variant, 1, 'Không phù hợp');

        $this->getJson("/api/products/{$product->id}/reviews?rating=5&has_images=1")
            ->assertOk()
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.id', $reviewWithImage->id)
            ->assertJsonPath('data.items.0.rating', 5)
            ->assertJsonPath('data.items.0.customer.name', $reviewWithImage->user->name)
            ->assertJsonPath('data.items.0.variant.sku', $variant->sku)
            ->assertJsonPath('data.items.0.variant.attributes.0.value_label', 'Đen')
            ->assertJsonPath('data.items.0.images.0.url', 'https://cdn.example.com/review.jpg');

        $this->getJson("/api/products/{$product->id}/reviews?sort=lowest_rating")
            ->assertOk()
            ->assertJsonPath('data.items.0.rating', 1)
            ->assertJsonPath('data.pagination.total', 4);
    }

    public function test_rating_summary_returns_average_and_distribution(): void
    {
        [$product, $variant] = $this->createProductWithVariant();

        foreach ([5, 5, 4, 1] as $rating) {
            $this->createReview($product, $variant, $rating);
        }

        $this->getJson("/api/products/{$product->id}/reviews/summary")
            ->assertOk()
            ->assertJsonPath('data.product_id', $product->id)
            ->assertJsonPath('data.average_rating', 3.75)
            ->assertJsonPath('data.total_reviews', 4)
            ->assertJsonPath('data.distribution.0.rating', 5)
            ->assertJsonPath('data.distribution.0.count', 2)
            ->assertJsonPath('data.distribution.0.percentage', 50)
            ->assertJsonPath('data.distribution.4.rating', 1)
            ->assertJsonPath('data.distribution.4.count', 1);
    }

    public function test_empty_product_rating_summary_returns_zero_values(): void
    {
        $product = Product::create([
            'name' => 'Sản phẩm chưa có đánh giá',
            'price' => 100000,
        ]);

        $this->getJson("/api/products/{$product->id}/reviews/summary")
            ->assertOk()
            ->assertJsonPath('data.average_rating', 0)
            ->assertJsonPath('data.total_reviews', 0)
            ->assertJsonPath('data.distribution.0.count', 0)
            ->assertJsonPath('data.distribution.0.percentage', 0);
    }

    public function test_creating_review_stores_the_product_id_instead_of_variant_id(): void
    {
        Product::create(['name' => 'Sản phẩm đệm ID', 'price' => 50000]);
        [$product, $variant] = $this->createProductWithVariant();
        $user = User::factory()->create();
        $order = $this->createOrder($user);
        $detail = $order->orderDetails()->create([
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'unit_price' => 100000,
            'unit_discount_price' => 0,
        ]);

        $this->actingAs($user, 'api')
            ->postJson("/api/order/{$order->id}/{$detail->id}/review", [
                'rating' => 5,
                'comment' => 'Đúng sản phẩm',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('reviews', [
            'order_detail_id' => $detail->id,
            'product_id' => $product->id,
        ]);
    }

    private function createProductWithVariant(): array
    {
        $product = Product::create([
            'name' => 'Áo sơ mi',
            'price' => 100000,
        ]);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-'.fake()->unique()->numerify('#####'),
            'price' => 100000,
            'stock' => 10,
        ]);

        return [$product, $variant];
    }

    private function createReview(
        Product $product,
        ProductVariant $variant,
        int $rating,
        ?string $comment = null
    ): Review {
        $user = User::factory()->create();
        $order = $this->createOrder($user);
        $detail = $order->orderDetails()->create([
            'product_variant_id' => $variant->id,
            'quantity' => 1,
            'unit_price' => 100000,
            'unit_discount_price' => 0,
        ]);

        return Review::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'order_detail_id' => $detail->id,
            'rating' => $rating,
            'comment' => $comment,
        ])->load('user');
    }

    private function createOrder(User $user): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'total_price' => 100000,
            'discount_price' => 0,
            'final_price' => 100000,
            'ship_price' => 0,
            'discount_ship_price' => 0,
            'status' => OrderStatus::COMPLETED->value,
            'ward_code' => '1003544',
            'ward_name' => 'Phường Test',
            'province_id' => 202,
            'province_name' => 'Hồ Chí Minh',
            'specific_address' => '02 Võ Oanh',
            'full_name' => $user->name,
            'phone' => '0900000000',
        ]);
    }
}
