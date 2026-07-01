<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = [
        'label',
        'title',
        'description',
        'image_url',
    ];
}
