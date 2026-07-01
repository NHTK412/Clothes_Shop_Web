<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('reviews')
            ->join('order_details', 'order_details.id', '=', 'reviews.order_detail_id')
            ->join('product_variants', 'product_variants.id', '=', 'order_details.product_variant_id')
            ->select('reviews.id', 'product_variants.product_id')
            ->orderBy('reviews.id')
            ->chunk(500, function ($reviews) {
                foreach ($reviews as $review) {
                    DB::table('reviews')
                        ->where('id', $review->id)
                        ->update(['product_id' => $review->product_id]);
                }
            });
    }

    public function down(): void
    {
        // The former incorrect product_id cannot be reconstructed safely.
    }
};
