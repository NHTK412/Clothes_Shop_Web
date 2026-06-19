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
    public function getItems(User $user): array
    {
        $cart = Cart::with([
            'items.productVariant.product',
            'items.productVariant.attributeValues.attributeType',
        ])->where('user_id', $user->id)->first();

        if (! $cart) {
            return [];
        }

        return $cart->items->map(function (CartItem $item) {
            $variant = $item->productVariant;
            $product = $variant?->product;

            return [
                'cart_item_id' => $item->id,
                'product_variant_id' => $variant?->id,
                'product_name' => $product?->name,
                'attributes' => $variant?->attributeValues->map(function ($attributeValue) {
                    return [
                        'type' => $attributeValue->attributeType?->name,
                        'display_type' => $attributeValue->attributeType?->display_name,
                        'value' => $attributeValue->value,
                        'display_value' => $attributeValue->display_value,
                    ];
                })->values()->toArray() ?? [],
                'image' => $variant?->image ?? $product?->image,
                'original_price' => $variant?->price,
                'discount_price' => $variant?->discount_price,
                'quantity' => $item->quantity,
            ];
        })->values()->toArray();
    }

    public function addItem(User $user, int $productVariantId, int $quantity = 1): array
    {
        return DB::transaction(function () use ($user, $productVariantId, $quantity) {
            $cart = Cart::where('user_id', $user->id)->lockForUpdate()->first();

            if (! $cart) {
                $cart = new Cart;
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
                $item = new CartItem;
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

    public function updateItem(User $user, int $cartItemId, int $quantity): bool
    {
        return DB::transaction(function () use ($user, $cartItemId, $quantity) {
            $item = CartItem::where('id', $cartItemId)
                ->whereHas('cart', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->lockForUpdate()
                ->firstOrFail();

            if ($quantity <= 0) {
                $item->delete();

                return true;
            }

            $variant = ProductVariant::where('id', $item->product_variant_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($variant->stock < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => "Maximum available quantity is {$variant->stock}.",
                ]);
            }

            $item->quantity = $quantity;
            $item->save();

            return false;
        });
    }

    public function getItemCount(User $user): int
    {
        $cart = Cart::where('user_id', $user->id)->first();

        if (! $cart) {
            return 0;
        }

        return $cart->items()->sum('quantity');
    }
}
