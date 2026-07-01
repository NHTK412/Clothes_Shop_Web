<?php

use App\Http\Controllers\AddressController;
use App\Http\Controllers\Api\AttributeTypeController;
use App\Http\Controllers\Api\AttributeValueController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\GhnController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PromotionController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\ReturnRequestController;
use App\Http\Controllers\ReviewController;
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

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/
Route::controller(ProductController::class)->group(function () {
    Route::get('/products', 'index')->name('products.index');
    Route::get('/products/{id}', 'show')->name('products.show');
});

Route::controller(ReviewController::class)->prefix('products/{product}/reviews')->name('products.reviews.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/summary', 'summary')->name('summary');
});

Route::controller(CategoryController::class)->prefix('categories')->name('categories.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/{id}', 'show')->name('show');
});

Route::controller(AttributeTypeController::class)->prefix('attributes')->name('attributes.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/{id}', 'show')->name('show');
});

Route::controller(AttributeValueController::class)->prefix('attributes/{attributeType}/values')->name('attributes.values.')->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/{attributeValue}', 'show')->name('show');
});

Route::controller(GhnController::class)->prefix('ghn')->name('ghn.')->group(function () {
    Route::get('/provinces', 'provinces')->name('provinces');
    Route::get('/districts', 'districts')->name('districts');
    Route::get('/wards', 'wards')->name('wards');
});

Route::post('/ghn/webhook/order-status', [OrderController::class, 'update'])->name('ghn.webhook.order-status');
Route::get('/vnpay/return', [VnpayController::class, 'return'])->name('vnpay.return');
Route::get('/promotion/first', [PromotionController::class, 'first'])->name('promotion.first');

/*
|--------------------------------------------------------------------------
| Authenticated customer routes
|--------------------------------------------------------------------------
*/
Route::middleware('auth:api')->group(function () {
    Route::controller(UserProfileController::class)->prefix('profile')->name('profile.')->group(function () {
        Route::get('/', 'show')->name('show');
        Route::put('/', 'update')->name('update');
        Route::patch('/', 'patch')->name('patch');
        Route::delete('/', 'destroy')->name('destroy');
    });

    Route::controller(AddressController::class)->prefix('addresses')->name('addresses.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::put('/{address}', 'update')->name('update');
        Route::delete('/{address}', 'destroy')->name('destroy');
        Route::put('/{address}/default', 'setDefault')->name('default');
    });

    Route::controller(ProductController::class)->group(function () {
        Route::post('/products/{productId}/favorites', 'addFavorite')->name('products.favorites.add');
        Route::delete('/products/{productId}/favorites', 'removeFavorite')->name('products.favorites.remove');
        Route::get('/users/{userId}/favorites', 'getUserFavorites')->name('users.favorites.index');
    });

    Route::controller(GhnController::class)->prefix('ghn')->name('ghn.')->group(function () {
        Route::get('/shipping-fee', 'shippingFee')->name('shipping-fee');
        Route::get('/detail', 'detail')->name('detail');
    });

    Route::controller(CartController::class)->prefix('cart/items')->name('cart.items.')->group(function () {
        Route::get('/', 'getItems')->name('index');
        Route::get('/count', 'getCountItem')->name('count');
        Route::post('/', 'addItem')->name('add');
        Route::put('/{cartItem}', 'updateItem')->name('update');
    });

    Route::controller(OrderController::class)->prefix('order')->name('order.')->group(function () {
        Route::post('/', 'store')->name('store');
        Route::get('/', 'index')->name('index');
        Route::get('/{order}', 'show')->name('show');
        Route::patch('/{order}/cancel', 'cancel')->name('cancel');
        Route::post('/{order}/{orderDetail}/review', 'review')->name('review');
    });

    Route::get('/voucher/{voucher}', [VoucherController::class, 'show'])->name('voucher.show');
    Route::post('/vnpay/payment-url', [VnpayController::class, 'createPaymentUrl'])->name('vnpay.payment-url');
    Route::post('/upload', [UploadController::class, 'uploadProductImage'])->name('upload.product-image');

    Route::controller(ReturnRequestController::class)->prefix('return-requests')->name('return-requests.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::get('/{returnRequest}', 'show')->name('show');
        Route::patch('/{returnRequest}/cancel', 'cancel')->name('cancel');
    });

    Route::get('/refunds', [RefundController::class, 'index'])->name('refunds.index');
});

/*
|--------------------------------------------------------------------------
| Administrator routes
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:api', 'admin'])->group(function () {
    Route::controller(ProductController::class)->group(function () {
        Route::post('/products', 'store')->name('products.store');
        Route::put('/products/{id}', 'update')->name('products.update');
        Route::delete('/products/{id}', 'destroy')->name('products.destroy');
    });

    Route::controller(CategoryController::class)->prefix('categories')->name('categories.')->group(function () {
        Route::post('/', 'store')->name('store');
        Route::put('/{id}', 'update')->name('update');
        Route::delete('/{id}', 'destroy')->name('destroy');
    });

    Route::controller(AttributeTypeController::class)->prefix('attributes')->name('attributes.')->group(function () {
        Route::post('/', 'store')->name('store');
        Route::put('/{id}', 'update')->name('update');
        Route::delete('/{id}', 'destroy')->name('destroy');
    });

    Route::controller(AttributeValueController::class)->prefix('attributes/{attributeType}/values')->name('attributes.values.')->group(function () {
        Route::post('/', 'store')->name('store');
        Route::put('/{attributeValue}', 'update')->name('update');
        Route::delete('/{attributeValue}', 'destroy')->name('destroy');
    });

    Route::controller(CustomerController::class)->prefix('customers')->name('customers.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/{customer}', 'show')->name('show');
        Route::patch('/{customer}', 'update')->name('update');
        Route::delete('/{customer}', 'destroy')->name('destroy');
    });

    Route::controller(OrderController::class)->prefix('admin')->name('admin.')->group(function () {
        Route::get('/orders', 'adminIndex')->name('orders.index');
        Route::get('/orders/summary', 'adminOrderSummary')->name('orders.summary');
        Route::get('/orders/{order}', 'adminShow')->name('orders.show');
        Route::get('/customers/{customer}/orders', 'adminOrdersByCustomer')->name('customers.orders.index');
    });

    Route::controller(ProductController::class)->prefix('admin/inventory')->name('admin.inventory.')->group(function () {
        Route::get('/', 'adminInventoryIndex')->name('index');
        Route::patch('/{productVariant}', 'adminInventoryUpdate')->name('update');
        Route::post('/stock-in', 'adminInventoryStockIn')->name('stock-in');
        Route::post('/stock-out', 'adminInventoryStockOut')->name('stock-out');
    });

    Route::controller(VoucherController::class)->prefix('voucher')->name('voucher.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::put('/{voucher}', 'update')->name('update');
        Route::delete('/{voucher}', 'destroy')->name('destroy');
    });

    Route::controller(PromotionController::class)->prefix('promotion')->name('promotion.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::put('/{promotion}', 'update')->name('update');
        Route::delete('/{promotion}', 'destroy')->name('destroy');
    });

    Route::controller(ReturnRequestController::class)->prefix('admin/return-requests')->name('admin.return-requests.')->group(function () {
        Route::get('/', 'adminIndex')->name('index');
        Route::get('/{returnRequest}', 'adminShow')->name('show');
        Route::patch('/{returnRequest}/status', 'updateStatus')->name('status');
    });

    Route::controller(RefundController::class)->prefix('admin/refunds')->name('admin.refunds.')->group(function () {
        Route::get('/', 'adminIndex')->name('index');
        Route::patch('/{refund}/status', 'updateStatus')->name('status');
        Route::patch('/{refund}', 'update')->name('update');
    });

    // Route::post('/upload', [UploadController::class, 'uploadProductImage'])->name('upload.product-image');

    Route::post('ghn/print-label', [GhnController::class, 'printLabel'])->name('ghn.print-label');
});
