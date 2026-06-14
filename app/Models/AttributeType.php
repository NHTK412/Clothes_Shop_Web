<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttributeType extends Model
{
    protected $fillable = ['name'];

    public function attributeValues()
    {
        return $this->hasMany(AttributeValue::class);
    }

}
