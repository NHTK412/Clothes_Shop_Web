<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    public function oauthProvider()
    {
        return $this->hasMany(OAuthProvider::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
