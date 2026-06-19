<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewImage extends Model
{
    protected $table = 'review_images';

    protected $fillable = [
        'image_path',
        'review_id',
    ];

    public function review()
    {
        return $this->belongsTo(Review::class);
    }
}
