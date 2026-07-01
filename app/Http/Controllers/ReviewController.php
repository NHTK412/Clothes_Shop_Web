<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReviewController extends Controller
{
    public function index(Request $request, Product $product)
    {
        $validated = $request->validate([
            'rating' => 'nullable|integer|min:1|max:5',
            'has_images' => 'nullable|boolean',
            'sort' => ['nullable', Rule::in([
                'newest',
                'oldest',
                'highest_rating',
                'lowest_rating',
            ])],
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = $product->reviews()->with([
            'user:id,name,avatar',
            'images:id,review_id,image_path',
            'orderDetail.productVariant.attributeValues.attributeType',
        ]);

        if (isset($validated['rating'])) {
            $query->where('rating', $validated['rating']);
        }

        if (array_key_exists('has_images', $validated)) {
            $validated['has_images']
                ? $query->whereHas('images')
                : $query->whereDoesntHave('images');
        }

        match ($validated['sort'] ?? 'newest') {
            'oldest' => $query->oldest(),
            'highest_rating' => $query->orderByDesc('rating')->latest('id'),
            'lowest_rating' => $query->orderBy('rating')->latest('id'),
            default => $query->latest(),
        };

        $reviews = $query->paginate(
            $validated['per_page'] ?? 10,
            ['*'],
            'page',
            $validated['page'] ?? 1
        );

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => [
                'items' => collect($reviews->items())
                    ->map(fn (Review $review) => $this->format($review))
                    ->values(),
                'pagination' => [
                    'current_page' => $reviews->currentPage(),
                    'per_page' => $reviews->perPage(),
                    'total' => $reviews->total(),
                    'last_page' => $reviews->lastPage(),
                ],
            ],
        ]);
    }

    public function summary(Product $product)
    {
        $counts = $product->reviews()
            ->selectRaw('rating, COUNT(*) as total')
            ->groupBy('rating')
            ->pluck('total', 'rating');

        $totalReviews = (int) $counts->sum();
        $ratingTotal = $counts->sum(
            fn ($count, $rating) => (int) $count * (int) $rating
        );
        $averageRating = $totalReviews > 0
            ? round($ratingTotal / $totalReviews, 2)
            : 0.0;

        $distribution = collect(range(5, 1))
            ->map(function (int $rating) use ($counts, $totalReviews) {
                $count = (int) ($counts[$rating] ?? 0);

                return [
                    'rating' => $rating,
                    'count' => $count,
                    'percentage' => $totalReviews > 0
                        ? round($count * 100 / $totalReviews, 2)
                        : 0.0,
                ];
            })
            ->values();

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => [
                'product_id' => $product->id,
                'average_rating' => $averageRating,
                'total_reviews' => $totalReviews,
                'distribution' => $distribution,
            ],
        ]);
    }

    private function format(Review $review): array
    {
        $variant = $review->orderDetail?->productVariant;

        return [
            'id' => $review->id,
            'rating' => (int) $review->rating,
            'comment' => $review->comment,
            'customer' => $review->user ? [
                'id' => $review->user->id,
                'name' => $review->user->name,
                'avatar' => $review->user->avatar,
            ] : null,
            'variant' => $variant ? [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'attributes' => $variant->attributeValues
                    ->map(fn ($value) => [
                        'type' => $value->attributeType?->name,
                        'type_label' => $value->attributeType?->display_name,
                        'value' => $value->value,
                        'value_label' => $value->display_value,
                    ])
                    ->values(),
            ] : null,
            'images' => $review->images
                ->map(fn ($image) => [
                    'id' => $image->id,
                    'url' => $image->image_path,
                ])
                ->values(),
            'created_at' => $review->created_at?->toISOString(),
            'updated_at' => $review->updated_at?->toISOString(),
        ];
    }
}
