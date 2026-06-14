<?php

use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index']);
Route::get('/cache-value', [HomeController::class, 'getCacheValue']);
Route::get('/welcome', function () {
    return view('welcome');
});

Route::get('/login', function () {
    return view('login');
});

Route::post('/login', function () {
    if (request('email') === 'admin' && request('password') === 'password') {
        return redirect('/welcome');
    }

    return back()->withErrors(['message' => 'Invalid credentials']);

});

Route::get('/api-docs.yaml', function () {
    return response()->file(storage_path('api-docs/api-docs.yaml'), [
        'Content-Type' => 'application/x-yaml'
    ]);
});

// Swagger UI viewer (uses CDN-swagger-ui, loads /api-docs.yaml)
Route::get('/swagger', function () {
    return view('swagger');
});
