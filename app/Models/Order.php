<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'total_price',
        'discount_price',
        'final_price',
        'ship_price',
        'discount_ship_price',
        'status',
        'ward_code',
        'ward_name',
        'province_id',
        'province_name',
        'specific_address',
        'full_name',
        'phone',
        'ghn_order_code',

        'voucher_id',
        'voucher_code',
        'voucher_discount_amount',
        'voucher_max_discount_amount',
        'voucher_type',

    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orderDetails()
    {
        return $this->hasMany(OrderDetail::class);
    }

    public function payment()
    {
        return $this->hasOne(Payment::class);
    }

    public function refundRequest()
    {
        return $this->hasOne(RefundRequest::class);
    }

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }
}
