<?php

namespace Tests\Feature;

use App\Models\AttributeType;
use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('discount_price', 10, 2)->nullable();
            $table->string('image')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->decimal('price', 10, 2);
            $table->decimal('discount_price', 10, 2)->nullable();
            $table->integer('stock')->default(0);
            $table->string('image')->nullable();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('attribute_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('display_name')->nullable();
            $table->timestamps();
        });

        Schema::create('attribute_values', function (Blueprint $table) {
            $table->id();
            $table->string('value');
            $table->string('display_value');
            $table->json('meta_data')->nullable();
            $table->foreignId('attribute_type_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('attribute_value_product_variant', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_variant_id', 'attribute_value_id']);
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('category_product', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_id', 'category_id']);
        });

        $admin = new User;
        $admin->forceFill(['id' => 1, 'role' => 'ROLE_ADMIN']);
        $this->actingAs($admin, 'api');
    }

    public function test_variant_uses_its_own_image_or_falls_back_to_product_image(): void
    {
        $productImage = 'https://cdn.example.com/products/shirt.jpg';
        $variantImage = 'https://cdn.example.com/products/shirt-black.jpg';

        $response = $this->postJson('/api/products', [
            'name' => 'Shirt',
            'price' => 199000,
            'image' => $productImage,
            'variants' => [
                [
                    'sku' => 'SHIRT-BLACK-M',
                    'price' => 199000,
                    'discount_price' => 179000,
                    'stock' => 10,
                    'image' => $variantImage,
                ],
                [
                    'sku' => 'SHIRT-WHITE-M',
                    'price' => 199000,
                    'stock' => 10,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.items.variants.0.image', $variantImage)
            ->assertJsonPath('data.items.variants.1.image', $productImage);

        $this->assertDatabaseHas('product_variants', [
            'sku' => 'SHIRT-BLACK-M',
            'discount_price' => 179000,
            'image' => $variantImage,
        ]);
        $this->assertDatabaseHas('product_variants', [
            'sku' => 'SHIRT-WHITE-M',
            'image' => $productImage,
        ]);
    }

    public function test_update_accepts_the_same_variant_fields_without_requiring_sku_again(): void
    {
        $createResponse = $this->postJson('/api/products', [
            'name' => 'Shirt',
            'price' => 199000,
            'image' => 'https://cdn.example.com/products/shirt.jpg',
            'variants' => [
                [
                    'sku' => 'SHIRT-BLACK-L',
                    'price' => 199000,
                    'stock' => 10,
                ],
            ],
        ])->assertCreated();

        $productId = $createResponse->json('data.items.id');
        $variantId = $createResponse->json('data.items.variants.0.id');
        $variantImage = 'https://cdn.example.com/products/shirt-black-updated.jpg';

        $this->putJson("/api/products/{$productId}", [
            'name' => 'Updated shirt',
            'variants' => [
                [
                    'id' => $variantId,
                    'price' => 209000,
                    'discount_price' => 189000,
                    'stock' => 7,
                    'image' => $variantImage,
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('data.items.name', 'Updated shirt')
            ->assertJsonPath('data.items.variants.0.discount_price', 189000)
            ->assertJsonPath('data.items.variants.0.image', $variantImage);

        $this->assertDatabaseHas('product_variants', [
            'id' => $variantId,
            'sku' => 'SHIRT-BLACK-L',
            'price' => 209000,
            'discount_price' => 189000,
            'stock' => 7,
            'image' => $variantImage,
        ]);
    }

    public function test_customer_cannot_create_a_product(): void
    {
        $customer = new User;
        $customer->forceFill(['id' => 2, 'role' => 'ROLE_CUSTOMER']);

        $this->actingAs($customer, 'api')
            ->postJson('/api/products', [
                'name' => 'Restricted product',
                'price' => 100000,
            ])
            ->assertForbidden();
    }

    public function test_products_can_be_filtered_by_attribute_value_ids_or_attribute_names(): void
    {
        $color = AttributeType::create(['name' => 'color', 'display_name' => 'Màu sắc']);
        $size = AttributeType::create(['name' => 'size', 'display_name' => 'Kích thước']);

        $red = AttributeValue::create([
            'attribute_type_id' => $color->id,
            'value' => 'red',
            'display_value' => 'Đỏ',
        ]);
        $blue = AttributeValue::create([
            'attribute_type_id' => $color->id,
            'value' => 'blue',
            'display_value' => 'Xanh',
        ]);
        $medium = AttributeValue::create([
            'attribute_type_id' => $size->id,
            'value' => 'M',
            'display_value' => 'M',
        ]);
        $large = AttributeValue::create([
            'attribute_type_id' => $size->id,
            'value' => 'L',
            'display_value' => 'L',
        ]);

        $matchingProduct = Product::create(['name' => 'Red medium shirt', 'price' => 199000]);
        $matchingVariant = ProductVariant::create([
            'product_id' => $matchingProduct->id,
            'sku' => 'RED-M',
            'price' => 199000,
            'stock' => 10,
        ]);
        $matchingVariant->attributeValues()->sync([$red->id, $medium->id]);

        $nonMatchingProduct = Product::create(['name' => 'Mixed shirt', 'price' => 199000]);
        $redLargeVariant = ProductVariant::create([
            'product_id' => $nonMatchingProduct->id,
            'sku' => 'RED-L',
            'price' => 199000,
            'stock' => 10,
        ]);
        $redLargeVariant->attributeValues()->sync([$red->id, $large->id]);
        $blueMediumVariant = ProductVariant::create([
            'product_id' => $nonMatchingProduct->id,
            'sku' => 'BLUE-M',
            'price' => 199000,
            'stock' => 10,
        ]);
        $blueMediumVariant->attributeValues()->sync([$blue->id, $medium->id]);

        $this->getJson("/api/products?per_page=0&attribute_value_ids={$red->id},{$medium->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $matchingProduct->id);

        $this->getJson('/api/products?per_page=0&attr[color]=red&attr[size]=M')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $matchingProduct->id);
    }

    public function test_soft_deleted_product_is_hidden_but_its_variant_is_preserved(): void
    {
        $createResponse = $this->postJson('/api/products', [
            'name' => 'Soft delete product',
            'price' => 150000,
            'variants' => [
                [
                    'sku' => 'SOFT-DELETE-M',
                    'price' => 150000,
                    'stock' => 5,
                ],
            ],
        ])->assertCreated();

        $productId = $createResponse->json('data.items.id');
        $variantId = $createResponse->json('data.items.variants.0.id');

        $this->deleteJson("/api/products/{$productId}")
            ->assertOk();

        $this->assertSoftDeleted('products', ['id' => $productId]);
        $this->assertDatabaseHas('product_variants', ['id' => $variantId]);
        $this->assertNull(ProductVariant::with('product')->findOrFail($variantId)->product);

        $variantWithDeletedProduct = ProductVariant::with([
            'product' => fn ($query) => $query->withTrashed(),
        ])->findOrFail($variantId);
        $this->assertSame($productId, $variantWithDeletedProduct->product->id);

        $this->getJson("/api/products/{$productId}")
            ->assertNotFound();

        $this->getJson('/api/products?per_page=0')
            ->assertOk()
            ->assertJsonCount(0, 'data.items');
    }
}
