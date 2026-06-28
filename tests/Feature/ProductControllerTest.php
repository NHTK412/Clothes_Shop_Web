<?php

namespace Tests\Feature;

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

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('category_product', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_id', 'category_id']);
        });
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
            'image' => $variantImage,
        ]);
        $this->assertDatabaseHas('product_variants', [
            'sku' => 'SHIRT-WHITE-M',
            'image' => $productImage,
        ]);
    }
}
