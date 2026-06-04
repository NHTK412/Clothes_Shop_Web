<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
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
