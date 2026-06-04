<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'login'])->name('login');

Route::post('/login', [AuthController::class, 'authenticate'])->name('login.authenticate');

Route::get('/', [AuthController::class, 'index'])->name('home');

Route::get('/logout', [AuthController::class, 'logout'])->name('logout');
