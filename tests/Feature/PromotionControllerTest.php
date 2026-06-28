<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PromotionControllerTest extends TestCase
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

        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('discount_amount', 10, 2);
            $table->enum('discount_type', ['percentage', 'fixed']);
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('promotion_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unique(['promotion_id', 'product_id']);
        });

        $admin = new User;
        $admin->forceFill(['id' => 1, 'role' => 'ROLE_ADMIN']);
        $this->actingAs($admin, 'api');
    }

    public function test_it_returns_a_paginated_and_filtered_promotion_list_with_products(): void
    {
        $product = new Product;
        $product->name = 'Áo thun basic';
        $product->price = 199000;
        $product->image = 'https://cdn.example.com/products/shirt.jpg';
        $product->save();

        $activePromotion = Promotion::create([
            'name' => 'Khuyến mãi mùa hè',
            'description' => 'Giảm giá áo thun',
            'discount_amount' => 20,
            'discount_type' => 'percentage',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
            'is_active' => true,
        ]);
        $activePromotion->products()->attach($product->id);

        Promotion::create([
            'name' => 'Khuyến mãi đã tắt',
            'discount_amount' => 50000,
            'discount_type' => 'fixed',
            'start_date' => now()->subDay(),
            'end_date' => now()->addDay(),
            'is_active' => false,
        ]);

        $this->getJson('/api/promotion?status=active&is_active=true&per_page=10')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $activePromotion->id)
            ->assertJsonPath('data.items.0.products.0.id', $product->id)
            ->assertJsonPath('data.pagination.page', 1)
            ->assertJsonPath('data.pagination.limit', 10)
            ->assertJsonPath('data.pagination.totalItems', 1)
            ->assertJsonPath('data.pagination.totalPages', 1)
            ->assertJsonCount(1, 'data.items');
    }

    public function test_it_validates_list_filters(): void
    {
        $this->getJson('/api/promotion?status=unknown&per_page=101')
            ->assertUnprocessable()
            ->assertJson([
                'status' => 422,
                'success' => false,
                'data' => null,
            ]);
    }
}
