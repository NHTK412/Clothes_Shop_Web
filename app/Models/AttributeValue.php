<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttributeValue extends Model
{
    protected $fillable = [
        'attribute_type_id',
        'value',
        'display_value',
        'meta_data',
    ];

    protected function casts(): array
    {
        return [
            'meta_data' => 'array',
        ];
    }

    public function attributeType()
    {
        return $this->belongsTo(AttributeType::class);
    }

    public function productVariants()
    {
        return $this->belongsToMany(ProductVariant::class, 'attribute_value_product_variant');
    }
}
