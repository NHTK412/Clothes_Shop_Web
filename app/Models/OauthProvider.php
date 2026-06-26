<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OauthProvider extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'provider',
        'provider_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
