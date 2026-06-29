<?php

namespace Tests\Feature;

use App\Models\AttributeValue;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AttributeControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('attribute_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name');
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

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->decimal('price', 10, 2);
            $table->integer('stock')->default(0);
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('attribute_value_product_variant', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained()->cascadeOnDelete();
            $table->primary(['product_variant_id', 'attribute_value_id']);
        });

        $admin = new User;
        $admin->forceFill(['id' => 1, 'role' => 'ROLE_ADMIN']);
        $this->actingAs($admin, 'api');
    }

    public function test_admin_can_complete_attribute_type_and_value_crud(): void
    {
        $typeResponse = $this->postJson('/api/attributes', [
            'name' => 'color',
            'display_name' => 'Màu sắc',
        ])->assertCreated()
            ->assertJsonPath('name', 'color')
            ->assertJsonCount(0, 'attribute_values');

        $typeId = $typeResponse->json('id');

        $this->putJson("/api/attributes/{$typeId}", [
            'display_name' => 'Màu sản phẩm',
        ])->assertOk()
            ->assertJsonPath('display_name', 'Màu sản phẩm')
            ->assertJsonCount(0, 'attribute_values');

        $valueResponse = $this->postJson("/api/attributes/{$typeId}/values", [
            'value' => 'white',
            'display_value' => 'Trắng',
            'meta_data' => ['hex' => '#FFFFFF'],
        ])->assertCreated()
            ->assertJsonPath('data.display_value', 'Trắng')
            ->assertJsonPath('data.meta_data.hex', '#FFFFFF');

        $valueId = $valueResponse->json('data.id');

        $this->getJson('/api/attributes/color/values')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->getJson("/api/attributes/{$typeId}/values/{$valueId}")
            ->assertOk()
            ->assertJsonPath('data.value', 'white');

        $this->putJson("/api/attributes/{$typeId}/values/{$valueId}", [
            'display_value' => 'Màu trắng',
        ])->assertOk()
            ->assertJsonPath('data.display_value', 'Màu trắng');

        $this->deleteJson("/api/attributes/{$typeId}/values/{$valueId}")
            ->assertOk();

        $this->deleteJson("/api/attributes/{$typeId}")
            ->assertOk();

        $this->assertDatabaseMissing('attribute_types', ['id' => $typeId]);
    }

    public function test_delete_reports_the_product_using_an_attribute_value(): void
    {
        $typeId = $this->postJson('/api/attributes', [
            'name' => 'size',
            'display_name' => 'Kích cỡ',
        ])->assertCreated()->json('id');

        $valueId = $this->postJson("/api/attributes/{$typeId}/values", [
            'value' => 'm',
            'display_value' => 'M',
        ])->assertCreated()->json('data.id');

        $product = new Product;
        $product->name = 'Áo thun';
        $product->price = 199000;
        $product->save();

        $variant = new ProductVariant;
        $variant->product_id = $product->id;
        $variant->sku = 'AO-THUN-M';
        $variant->price = 199000;
        $variant->stock = 10;
        $variant->save();

        AttributeValue::findOrFail($valueId)->productVariants()->attach($variant->id);

        $this->deleteJson("/api/attributes/{$typeId}/values/{$valueId}")
            ->assertStatus(409)
            ->assertJsonPath('data.usages.0.product_id', $product->id)
            ->assertJsonPath('data.usages.0.product_variant_id', $variant->id)
            ->assertJsonPath('data.usages.0.sku', 'AO-THUN-M');

        $this->deleteJson("/api/attributes/{$typeId}")
            ->assertStatus(409)
            ->assertJsonPath('data.usages.0.attribute_value_id', $valueId)
            ->assertJsonPath('data.usages.0.product_id', $product->id);

        $this->assertDatabaseHas('attribute_values', ['id' => $valueId]);
    }

    public function test_customer_cannot_manage_attributes(): void
    {
        $customer = new User;
        $customer->forceFill(['id' => 2, 'role' => 'ROLE_CUSTOMER']);

        $this->actingAs($customer, 'api')
            ->postJson('/api/attributes', [
                'name' => 'material',
                'display_name' => 'Chất liệu',
            ])
            ->assertForbidden();
    }
}
