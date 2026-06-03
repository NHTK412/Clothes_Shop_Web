<?php

use Illuminate\Database\Eloquent\Model;

class OauthProvider extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
