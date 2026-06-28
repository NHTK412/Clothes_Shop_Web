<?php

namespace Tests\Feature;

use App\Http\Services\PromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PromotionConflictTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_overlapping_promotions_for_the_same_product(): void
    {
        $productId = $this->createProduct();
        $service = app(PromotionService::class);

        $service->create($this->promotionData(
            $productId,
            '2026-07-01 08:00:00',
            '2026-07-01 12:00:00'
        ));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Promotion time conflicts');

        $service->create($this->promotionData(
            $productId,
            '2026-07-01 10:00:00',
            '2026-07-01 14:00:00'
        ));
    }

    public function test_it_allows_non_overlapping_promotions_for_the_same_product(): void
    {
        $productId = $this->createProduct();
        $service = app(PromotionService::class);

        $service->create($this->promotionData(
            $productId,
            '2026-07-01 08:00:00',
            '2026-07-01 12:00:00'
        ));

        $promotion = $service->create($this->promotionData(
            $productId,
            '2026-07-01 12:00:01',
            '2026-07-01 14:00:00'
        ));

        $this->assertDatabaseHas('promotion_product', [
            'promotion_id' => $promotion->id,
            'product_id' => $productId,
        ]);
    }

    private function createProduct(): int
    {
        return DB::table('products')->insertGetId([
            'name' => 'Test product',
            'price' => 100000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function promotionData(int $productId, string $startDate, string $endDate): array
    {
        return [
            'name' => "Promotion {$startDate}",
            'discount_amount' => 10,
            'discount_type' => 'percentage',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_active' => true,
            'product_ids' => [$productId],
        ];
    }
}
