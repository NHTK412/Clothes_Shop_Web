<?php

namespace App\Http\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function addItem(User $user, int $productVariantId, int $quantity = 1): array
    {
        return DB::transaction(function () use ($user, $productVariantId, $quantity) {
            $cart = Cart::where('user_id', $user->id)->lockForUpdate()->first();

            if (! $cart) {
                $cart = new Cart();
                $cart->user_id = $user->id;
                $cart->save();
            }

            $variant = ProductVariant::where('id', $productVariantId)
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
    }
}
