<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    private const DEFAULT_IMAGE = 'https://down-vn.img.susercontent.com/file/1234b2a2d4ccbcdc4357c818cf58a1f7';

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('image')->default(self::DEFAULT_IMAGE);
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        $admin = new User;
        $admin->forceFill(['id' => 1, 'role' => 'ROLE_ADMIN']);
        $this->actingAs($admin, 'api');
    }

    public function test_admin_can_complete_category_crud_with_database_default_and_custom_images(): void
    {
        $parentResponse = $this->postJson('/api/categories', [
            'name' => 'Áo',
        ])->assertCreated()
            ->assertJsonPath('data.items.image', self::DEFAULT_IMAGE);

        $parentId = $parentResponse->json('data.items.id');
        $customImage = 'https://cdn.example.com/categories/t-shirts.jpg';

        $childResponse = $this->postJson('/api/categories', [
            'name' => 'Áo thun',
            'parent_id' => $parentId,
            'image' => $customImage,
        ])->assertCreated()
            ->assertJsonPath('data.items.image', $customImage);

        $childId = $childResponse->json('data.items.id');

        $this->putJson("/api/categories/{$childId}", [
            'name' => 'Áo thun nam',
        ])->assertOk()
            ->assertJsonPath('data.items.name', 'Áo thun nam')
            ->assertJsonPath('data.items.image', $customImage);

        $newImage = 'https://cdn.example.com/categories/mens-t-shirts.jpg';
        $this->putJson("/api/categories/{$childId}", [
            'image' => $newImage,
        ])->assertOk()
            ->assertJsonPath('data.items.image', $newImage);

        $this->getJson("/api/categories/{$parentId}")
            ->assertOk()
            ->assertJsonPath('data.items.children.0.id', $childId);

        $this->getJson('/api/categories?per_page=0')
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.pagination', null);

        $this->deleteJson("/api/categories/{$childId}")
            ->assertOk();

        $this->assertSoftDeleted('categories', ['id' => $childId]);
        $this->getJson("/api/categories/{$childId}")->assertNotFound();
    }

    public function test_update_rejects_a_parent_that_would_create_a_category_cycle(): void
    {
        $parentId = $this->postJson('/api/categories', ['name' => 'Áo'])
            ->assertCreated()
            ->json('data.items.id');

        $childId = $this->postJson('/api/categories', [
            'name' => 'Áo thun',
            'parent_id' => $parentId,
        ])->assertCreated()
            ->json('data.items.id');

        $this->putJson("/api/categories/{$parentId}", [
            'parent_id' => $childId,
        ])->assertUnprocessable();

        $this->deleteJson("/api/categories/{$parentId}")
            ->assertOk();

        $this->assertSoftDeleted('categories', ['id' => $parentId]);
        $this->assertDatabaseHas('categories', [
            'id' => $childId,
            'parent_id' => null,
        ]);
    }

    public function test_customer_cannot_create_a_category(): void
    {
        $customer = new User;
        $customer->forceFill(['id' => 2, 'role' => 'ROLE_CUSTOMER']);

        $this->actingAs($customer, 'api')
            ->postJson('/api/categories', ['name' => 'Không được tạo'])
            ->assertForbidden();
    }
}
