<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BannerApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_and_delete_banner(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'ROLE_ADMIN'])->save();

        $created = $this->actingAs($admin, 'api')->postJson('/api/admin/banners', [
            'label' => 'Bộ sưu tập mới',
            'title' => 'Thanh lịch trong từng khoảnh khắc',
            'description' => 'Những thiết kế tối giản, hiện đại giúp bạn tự tin từ công sở đến những cuộc hẹn cuối tuần.',
            'image_url' => 'https://cdn.example.com/banners/new-collection.jpg',
        ]);

        $created->assertCreated()
            ->assertJsonPath('data.label', 'Bộ sưu tập mới')
            ->assertJsonPath('data.title', 'Thanh lịch trong từng khoảnh khắc');

        $bannerId = $created->json('data.id');

        $this->actingAs($admin, 'api')
            ->patchJson("/api/admin/banners/{$bannerId}", [
                'title' => 'Thanh lịch trong mọi khoảnh khắc',
                'image_url' => 'https://cdn.example.com/banners/updated.jpg',
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Thanh lịch trong mọi khoảnh khắc')
            ->assertJsonPath('data.image_url', 'https://cdn.example.com/banners/updated.jpg');

        $this->actingAs($admin, 'api')
            ->deleteJson("/api/admin/banners/{$bannerId}")
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->assertDatabaseMissing('banners', ['id' => $bannerId]);
    }

    public function test_banner_list_and_detail_are_public(): void
    {
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'ROLE_ADMIN'])->save();

        $bannerId = $this->actingAs($admin, 'api')->postJson('/api/admin/banners', [
            'label' => 'Bộ sưu tập mới',
            'title' => 'Thanh lịch trong từng khoảnh khắc',
            'description' => 'Thiết kế tối giản và hiện đại.',
            'image_url' => 'https://cdn.example.com/banners/hero.jpg',
        ])->json('data.id');

        $this->getJson('/api/banners')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', $bannerId)
            ->assertJsonPath('data.pagination', null);

        $this->getJson("/api/banners/{$bannerId}")
            ->assertOk()
            ->assertJsonPath('data.image_url', 'https://cdn.example.com/banners/hero.jpg');
    }

    public function test_customer_cannot_manage_banners_and_invalid_image_url_is_rejected(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer, 'api')->postJson('/api/admin/banners', [
            'label' => 'Bộ sưu tập mới',
            'title' => 'Banner không hợp lệ',
            'description' => 'Mô tả',
            'image_url' => 'not-a-url',
        ])->assertForbidden();

        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'ROLE_ADMIN'])->save();

        $this->actingAs($admin, 'api')->postJson('/api/admin/banners', [
            'label' => 'Bộ sưu tập mới',
            'title' => 'Banner không hợp lệ',
            'description' => 'Mô tả',
            'image_url' => 'not-a-url',
        ])->assertUnprocessable();
    }
}
