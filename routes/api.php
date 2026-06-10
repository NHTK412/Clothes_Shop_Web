<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// Route::post('/login', [AuthController::class, 'authenticateApi'])->name('login.authenticate');
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'authenticateApi'])->name('login.authenticate');
    Route::post('/register', [AuthController::class, 'registerApi'])->name('register.api');
    Route::post('/send-reset-link', [AuthController::class, 'sendResetPasswordLinkApi'])->name('send.reset.link.api');

    Route::get('/reset-password/{token}', function ($token) {
        return redirect(
            env('FRONTEND_URL')."/reset-password?token={$token}&email=".request('email')
        );
    })->name('password.reset');

    Route::post('/reset-password', [AuthController::class, 'resetPasswordApi'])->name('password.update.api');
});

Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/products/{id}', [ProductController::class, 'show'])->name('products.show');
