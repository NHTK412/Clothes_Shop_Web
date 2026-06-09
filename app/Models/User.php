<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory;

    public function oauthProvider()
    {
        return $this->hasMany(OAuthProvider::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
