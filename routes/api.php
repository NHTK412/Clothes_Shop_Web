<?php

use App\Http\Controllers\Api\AttributeTypeController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\GhnController;
use App\Http\Controllers\ProductController;
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
// Admin product management
Route::post('/products', [ProductController::class, 'store'])->name('products.store');
Route::put('/products/{id}', [ProductController::class, 'update'])->name('products.update');
// Category CRUD
Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
Route::get('/categories/{id}', [CategoryController::class, 'show'])->name('categories.show');
Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
Route::put('/categories/{id}', [CategoryController::class, 'update'])->name('categories.update');
Route::delete('/categories/{id}', [CategoryController::class, 'destroy'])->name('categories.destroy');

// Attribute types and values (CRUD)
Route::get('/attributes', [AttributeTypeController::class, 'index'])->name('attributes.index');
Route::get('/attributes/{id}', [AttributeTypeController::class, 'show'])->name('attributes.show');
Route::post('/attributes', [AttributeTypeController::class, 'store'])->name('attributes.store');
Route::put('/attributes/{id}', [AttributeTypeController::class, 'update'])->name('attributes.update');
Route::delete('/attributes/{id}', [AttributeTypeController::class, 'destroy'])->name('attributes.destroy');
Route::get('/attributes/{idOrName}/values', [AttributeTypeController::class, 'values'])->name('attributes.values');

Route::prefix('ghn')->group(function () {
    Route::get('/provinces', [GhnController::class, 'provinces'])->name('ghn.provinces');
    Route::get('/districts', [GhnController::class, 'districts'])->name('ghn.districts');
    Route::get('/wards', [GhnController::class, 'wards'])->name('ghn.wards');
});

Route::middleware('auth:api')->group(function () {
    Route::get('/ghn/shipping-fee', [GhnController::class, 'shippingFee'])->name('ghn.shipping-fee');

    Route::get('/addresses', [AddressController::class, 'index'])->name('addresses.index');
    Route::post('/addresses', [AddressController::class, 'store'])->name('addresses.store');
    Route::put('/addresses/{address}', [AddressController::class, 'update'])->name('addresses.update');
    Route::delete('/addresses/{address}', [AddressController::class, 'destroy'])->name('addresses.destroy');
    Route::put('/addresses/{address}/default', [AddressController::class, 'setDefault'])->name('addresses.default');

    Route::get('/cart/items', [CartController::class, 'getItems'])->name('cart.items.index');
    Route::get('/cart/items/count', [CartController::class, 'getCountItem'])->name('cart.items.count');
    Route::post('/cart/items', [CartController::class, 'addItem'])->name('cart.items.add');
    Route::put('/cart/items/{cartItem}', [CartController::class, 'updateItem'])->name('cart.items.update');
});


Route::middleware('auth:api')->prefix('order')->group(function () {
    Route::post('/', [\App\Http\Controllers\OrderController::class, 'store'])->name('order.store');
});

