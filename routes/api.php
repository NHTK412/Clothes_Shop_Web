<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\Api\AttributeTypeController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\GhnController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\VnpayController;
use App\Http\Controllers\VoucherController;
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

    Route::post('/oauth2', [AuthController::class, 'oauth2Login'])->name('oauth2.login');
});

Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::get('/products/{id}', [ProductController::class, 'show'])->name('products.show');
// Admin product management
Route::post('/products', [ProductController::class, 'store'])->name('products.store');
Route::put('/products/{id}', [ProductController::class, 'update'])->name('products.update');
Route::delete('/products/{id}', [ProductController::class, 'destroy'])->name('products.destroy');
// Category CRUD
Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
Route::get('/categories/{id}', [CategoryController::class, 'show'])->name('categories.show');
Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
Route::put('/categories/{id}', [CategoryController::class, 'update'])->name('categories.update');
Route::delete('/categories/{id}', [CategoryController::class, 'destroy'])->name('categories.destroy');

Route::post('/products/{productId}/favorites', [ProductController::class, 'addFavorite'])->name('products.favorites.add');
Route::delete('/products/{productId}/favorites', [ProductController::class, 'removeFavorite'])->name('products.favorites.remove');
Route::get('/users/{userId}/favorites', [ProductController::class, 'getUserFavorites'])->name('users.favorites.index');

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
    Route::post('/webhook/order-status', [OrderController::class, 'update'])->name('ghn.webhook.order-status');
});

Route::get('/vnpay/return', [VnpayController::class, 'return'])->name('vnpay.return');

Route::middleware('auth:api')->group(function () {
    Route::get('/ghn/shipping-fee', [GhnController::class, 'shippingFee'])->name('ghn.shipping-fee');
    Route::get('/ghn/detail', [GhnController::class, 'detail'])->name('ghn.detail');

    Route::get('/profile', [UserProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [UserProfileController::class, 'update'])->name('profile.update');
    Route::patch('/profile', [UserProfileController::class, 'patch'])->name('profile.patch');
    Route::delete('/profile', [UserProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/addresses', [AddressController::class, 'index'])->name('addresses.index');
    Route::post('/addresses', [AddressController::class, 'store'])->name('addresses.store');
    Route::put('/addresses/{address}', [AddressController::class, 'update'])->name('addresses.update');
    Route::delete('/addresses/{address}', [AddressController::class, 'destroy'])->name('addresses.destroy');
    Route::put('/addresses/{address}/default', [AddressController::class, 'setDefault'])->name('addresses.default');

    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::patch('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');

    Route::get('/admin/orders', [OrderController::class, 'adminIndex'])->name('admin.orders.index');
    Route::get('/admin/orders/summary', [OrderController::class, 'adminOrderSummary'])->name('admin.orders.summary');
    Route::get('/admin/orders/{order}', [OrderController::class, 'adminShow'])->name('admin.orders.show');
    Route::get('/admin/customers/{customer}/orders', [OrderController::class, 'adminOrdersByCustomer'])->name('admin.customers.orders.index');
    Route::get('/admin/inventory', [ProductController::class, 'adminInventoryIndex'])->name('admin.inventory.index');
    Route::patch('/admin/inventory/{productVariant}', [ProductController::class, 'adminInventoryUpdate'])->name('admin.inventory.update');
    Route::post('/admin/inventory/stock-in', [ProductController::class, 'adminInventoryStockIn'])->name('admin.inventory.stock-in');
    Route::post('/admin/inventory/stock-out', [ProductController::class, 'adminInventoryStockOut'])->name('admin.inventory.stock-out');

    Route::get('/cart/items', [CartController::class, 'getItems'])->name('cart.items.index');
    Route::get('/cart/items/count', [CartController::class, 'getCountItem'])->name('cart.items.count');
    Route::post('/cart/items', [CartController::class, 'addItem'])->name('cart.items.add');
    Route::put('/cart/items/{cartItem}', [CartController::class, 'updateItem'])->name('cart.items.update');

    Route::post('/vnpay/payment-url', [VnpayController::class, 'createPaymentUrl'])->name('vnpay.payment-url');
});

Route::middleware('auth:api')->prefix('order')->group(function () {
    Route::post('/', [OrderController::class, 'store'])->name('order.store');
    Route::get('/', [OrderController::class, 'index'])->name('order.index');
    Route::get('/{order}', [OrderController::class, 'show'])->name('order.show');
    Route::patch('/{order}/cancel', [OrderController::class, 'cancel'])->name('order.cancel');
    Route::post('/{order}/{orderDetail}/review', [OrderController::class, 'review'])->name('order.review');
});

Route::middleware('auth:api')->prefix('upload')->group(function () {
    Route::post('/', [UploadController::class, 'uploadProductImage'])->name('upload.product-image');
});

Route::middleware('auth:api')->prefix('voucher')->group(function () {
    // Route::post('/', [VoucherController::class, 'store'])->name('voucher.store');
    // Route::get('/', [VoucherController::class, 'index'])->name('voucher.index');
    Route::get('/{voucher}', [VoucherController::class, 'show'])->name('voucher.show');
    // Route::put('/{voucher}', [VoucherController::class, 'update'])->name('voucher.update');
    // Route::delete('/{voucher}', [VoucherController::class, 'destroy'])->name('voucher.destroy');
});
