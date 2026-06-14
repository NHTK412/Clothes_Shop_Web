<?php

/**
 * @OA\Info(
 *     version="1.0.0",
 *     title="Clothes Shop API",
 *     description="API documentation for Clothes Shop Web",
 * )
 */

use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\Api\AttributeTypeController;
use Illuminate\Support\Facades\Route;

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
