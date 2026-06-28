<?php

namespace App\Http\Controllers;

use App\Http\Services\PromotionService;
use App\Models\Promotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    private PromotionService $promotionService;

    public function __construct(PromotionService $promotionService)
    {
        $this->promotionService = $promotionService;
    }

    public function first()
    {
        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => $this->promotionService->first(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'discount_amount' => 'required|numeric|gt:0',
            'discount_type' => 'required|in:percentage,fixed',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_active' => 'sometimes|boolean',
            'product_ids' => 'required|array|min:1',
            'product_ids.*' => 'required|integer|distinct|exists:products,id',
        ]);

        return response()->json([
            'status' => 201,
            'success' => true,
            'message' => null,
            'data' => $this->promotionService->create($data),
        ], 201);
    }

    public function update(Request $request, Promotion $promotion): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'discount_amount' => 'sometimes|required|numeric|gt:0',
            'discount_type' => 'sometimes|required|in:percentage,fixed',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date',
            'is_active' => 'sometimes|boolean',
            'product_ids' => 'sometimes|required|array|min:1',
            'product_ids.*' => 'required|integer|distinct|exists:products,id',
        ]);

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => $this->promotionService->update($promotion, $data),
        ]);
    }

    public function destroy(Promotion $promotion): JsonResponse
    {
        $this->promotionService->delete($promotion);

        return response()->json([
            'status' => 200,
            'success' => true,
            'message' => null,
            'data' => true,
        ]);
    }
}
