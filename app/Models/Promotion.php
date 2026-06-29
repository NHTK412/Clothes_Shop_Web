<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Promotion extends Model
{
    protected $fillable = [
        'name',
        'description',
        'discount_amount',
        'discount_type',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_amount' => 'decimal:2',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'promotion_product', 'promotion_id', 'product_id');
    }
}
