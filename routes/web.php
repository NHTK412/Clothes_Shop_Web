<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/login', [AuthController::class, 'login'])->name('login');

Route::post('/login', [AuthController::class, 'authenticate'])->name('login.authenticate');

Route::get('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/register', [AuthController::class, 'registerFormUI'])->name('register');

Route::post('/register', [AuthController::class, 'register'])->name('register.submit');

Route::get('/', [AuthController::class, 'index'])->name('home');
