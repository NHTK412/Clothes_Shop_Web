<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'method',
        'status',
        'transaction_id',
        'payment_details',
    ];

    protected $casts = [
        'payment_details' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
