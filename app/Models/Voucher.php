<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    protected $fillable = [
        'code',
        'description',
        'discount_amount',
        'max_discount_amount',
        'discount_type',
        'is_active',
        'usage_limit',
        'expiry_date',
    ];

    public function applyToOrder($order)
    {
        switch ($this->discount_type) {
            case 'ORDER':
                $currentDiscount = (float) $order->discount_price;
                $baseAmount = max((float) $order->total_price - $currentDiscount, 0);
                $discount = $baseAmount * ((float) $this->discount_amount / 100);

                if ($this->max_discount_amount !== null) {
                    $discount = min($discount, (float) $this->max_discount_amount);
                }

                $discount = min($discount, $baseAmount);
                $order->discount_price = $currentDiscount + $discount;
                break;
            case 'SHIPPING':
                $shipPrice = (float) $order->ship_price;
                $currentShipDiscount = (float) $order->discount_ship_price;
                $baseAmount = max($shipPrice - $currentShipDiscount, 0);
                $discount = $baseAmount * ((float) $this->discount_amount / 100);

                if ($this->max_discount_amount !== null) {
                    $discount = min($discount, (float) $this->max_discount_amount);
                }

                $discount = min($discount, $baseAmount);
                $order->discount_ship_price = $currentShipDiscount + $discount;
                break;
            default:
                throw new \Exception('Invalid discount type');
        }

        $order->final_price = (float) $order->total_price
            + (float) $order->ship_price
            - (float) $order->discount_price
            - (float) $order->discount_ship_price;
    }
}
