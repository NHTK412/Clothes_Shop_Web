<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttributeValue extends Model
{
    public function attributeType()
    {
        return $this->belongsTo(AttributeType::class);
    }
}
