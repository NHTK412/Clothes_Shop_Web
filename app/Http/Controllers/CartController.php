<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class CartController extends Controller
{
    #[OA\Post(
        path: '/api/cart/items',
        summary: 'Add product variant to cart',
        security: [['bearerAuth' => []]],
        tags: ['Cart'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['product_variant_id'],
                properties: [
                    new OA\Property(property: 'product_variant_id', type: 'integer', example: 1),
                    new OA\Property(property: 'quantity', type: 'integer', example: 1),
                ],
                type: 'object'
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Cart item quantity updated'),
            new OA\Response(response: 201, description: 'Product variant added to cart'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Invalid data or not enough stock'),
        ]
    )]
    public function addItem(Request $request)
    {
        $validated = $request->validate([
            'product_variant_id' => 'required|integer|exists:product_variants,id',
            'quantity' => 'nullable|integer|min:1',
        ]);

        $quantity = $validated['quantity'] ?? 1;
        $user = $request->user();

        [$cart, $created] = DB::transaction(function () use ($user, $validated, $quantity) {
            $cart = Cart::where('user_id', $user->id)->lockForUpdate()->first();

            if (! $cart) {
                $cart = new Cart();
                $cart->user_id = $user->id;
                $cart->save();
            }

            $variant = ProductVariant::where('id', $validated['product_variant_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $item = CartItem::where('cart_id', $cart->id)
                ->where('product_variant_id', $variant->id)
                ->first();

            $newQuantity = ($item?->quantity ?? 0) + $quantity;

            if ($variant->stock < $newQuantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'Requested quantity exceeds available stock.',
                ]);
            }

            $created = false;

            if (! $item) {
                $item = new CartItem();
                $item->cart_id = $cart->id;
                $item->product_variant_id = $variant->id;
                $created = true;
            }

            $item->quantity = $newQuantity;
            $item->save();

            $cart->load(['items.productVariant.product']);

            return [$cart, $created];
        });

        $statusCode = $created ? 201 : 200;

        return response()->json([
            'status' => $statusCode,
            'success' => true,
            'message' => null,
            'data' => [
                'items' => $cart->toArray(),
                'pagination' => null,
            ],
        ], $statusCode);
    }
}
