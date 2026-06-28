<?php

namespace App\Http\Services;

use App\Models\Product;
use App\Models\Promotion;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PromotionService
{
    public function first()
    {
        $promotion = Promotion::where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->where('is_active', true)
            ->orderBy('start_date', 'desc')
            ->first();

        return $promotion;
    }

    public function create(array $data): Promotion
    {
        return DB::transaction(function () use ($data) {
            $productIds = $this->lockProducts($data['product_ids']);
            $isActive = $data['is_active'] ?? true;

            $this->validatePeriod($data['start_date'], $data['end_date']);

            if ($isActive) {
                $this->ensureNoOverlap(
                    $productIds,
                    $data['start_date'],
                    $data['end_date']
                );
            }

            $promotion = Promotion::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'discount_amount' => $data['discount_amount'],
                'discount_type' => $data['discount_type'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'is_active' => $isActive,
            ]);

            $promotion->products()->sync($productIds);

            return $promotion->load('products');
        }, 3);
    }

    public function update(Promotion $promotion, array $data): Promotion
    {
        return DB::transaction(function () use ($promotion, $data) {
            $currentProductIds = $promotion->products()->pluck('products.id')->all();
            $productIds = $data['product_ids'] ?? $currentProductIds;

            // Lock both the old and new products so concurrent promotion edits
            // involving any of them are serialized.
            $this->lockProducts(array_merge($currentProductIds, $productIds));

            $startDate = $data['start_date'] ?? $promotion->start_date;
            $endDate = $data['end_date'] ?? $promotion->end_date;
            $isActive = $data['is_active'] ?? $promotion->is_active;

            $this->validatePeriod($startDate, $endDate);

            if ($isActive) {
                $this->ensureNoOverlap(
                    $productIds,
                    $startDate,
                    $endDate,
                    $promotion->id
                );
            }

            $promotion->fill([
                'name' => $data['name'] ?? $promotion->name,
                'description' => array_key_exists('description', $data)
                    ? $data['description']
                    : $promotion->description,
                'discount_amount' => $data['discount_amount'] ?? $promotion->discount_amount,
                'discount_type' => $data['discount_type'] ?? $promotion->discount_type,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'is_active' => $isActive,
            ])->save();

            if (array_key_exists('product_ids', $data)) {
                $promotion->products()->sync($productIds);
            }

            return $promotion->load('products');
        }, 3);
    }


    public function syncDiscountPrices(): int
    {
        $updatedProducts = 0;

        Product::query()
            ->whereHas('promotions')
            ->select('products.id')
            ->chunkById(100, function ($products) use (&$updatedProducts) {
                foreach ($products as $productReference) {
                    DB::transaction(function () use ($productReference, &$updatedProducts) {
                        $product = Product::query()
                            ->lockForUpdate()
                            ->find($productReference->id);

                        if (! $product) {
                            return;
                        }

                        $variants = $product->variants()
                            ->orderBy('id')
                            ->lockForUpdate()
                            ->get();

                        $promotions = $product->promotions()
                            ->where('is_active', true)
                            ->where('start_date', '<=', now())
                            ->where('end_date', '>=', now())
                            ->limit(2)
                            ->get();

                        if ($promotions->count() > 1) {
                            throw new \LogicException(
                                "Product {$product->id} has overlapping active promotions."
                            );
                        }

                        $promotion = $promotions->first();

                        $product->discount_price = $this->promotionDiscount(
                            (float) $product->price,
                            $promotion
                        );
                        $product->save();

                        foreach ($variants as $variant) {
                            $variant->discount_price = $this->promotionDiscount(
                                (float) $variant->price,
                                $promotion
                            );
                            $variant->save();
                        }

                        $updatedProducts++;
                    }, 3);
                }
            });

        return $updatedProducts;
    }

    private function lockProducts(array $productIds): array
    {
        $productIds = collect($productIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $lockedProductIds = Product::query()
            ->whereKey($productIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->pluck('id')
            ->all();

        if (count($lockedProductIds) !== count($productIds)) {
            throw ValidationException::withMessages([
                'product_ids' => 'One or more selected products do not exist.',
            ]);
        }

        return $productIds;
    }

    private function validatePeriod($startDate, $endDate): void
    {
        if (CarbonImmutable::parse($endDate)->lessThanOrEqualTo(CarbonImmutable::parse($startDate))) {
            throw ValidationException::withMessages([
                'end_date' => 'Promotion end_date must be after start_date.',
            ]);
        }
    }

    private function ensureNoOverlap(
        array $productIds,
        $startDate,
        $endDate,
        ?int $ignorePromotionId = null
    ): void {
        $conflicts = Promotion::query()
            ->when(
                $ignorePromotionId,
                fn ($query) => $query->whereKeyNot($ignorePromotionId)
            )
            ->where('is_active', true)
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->whereHas('products', fn ($query) => $query->whereKey($productIds))
            ->with([
                'products' => fn ($query) => $query->whereKey($productIds),
            ])
            ->get();

        if ($conflicts->isEmpty()) {
            return;
        }

        $details = $conflicts->map(function (Promotion $promotion) {
            $ids = $promotion->products->pluck('id')->implode(', ');

            return "{$promotion->name} (#{$promotion->id}) on products [{$ids}]";
        })->implode('; ');

        throw ValidationException::withMessages([
            'product_ids' => "Promotion time conflicts with: {$details}.",
        ]);
    }

    private function promotionDiscount(float $originalPrice, ?Promotion $promotion): ?float
    {
        if (! $promotion) {
            return null;
        }

        if ($promotion->discount_type === 'percentage') {
            $discount = $originalPrice * ((float) $promotion->discount_amount / 100);
        } else {
            $discount = (float) $promotion->discount_amount;
        }

        return round(min(max((float) $discount, 0), $originalPrice), 2);
    }
}
