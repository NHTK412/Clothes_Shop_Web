<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class HomeController extends Controller
{
    public function index()
    {
        return view('welcome');
    }

    public function getCacheValue()
    {
        return response()->json(['value' => Cache::get('key')]);
    }
}
