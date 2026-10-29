<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AdminRegistrationController;
use App\Http\Controllers\Api\AdminSettingsController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\ShopRegistrationController;
use App\Http\Middleware\ProtectPublicForm;
use App\Http\Middleware\ResolveShop;
use Illuminate\Support\Facades\Route;

Route::get('/admin/registration', [AdminRegistrationController::class, 'status']);
Route::post('/admin/register', [AdminRegistrationController::class, 'store'])->middleware('throttle:3,1');

Route::post('/admin/login', [AdminController::class, 'login'])->middleware('throttle:5,1');
Route::post('/admin/register-shop', [ShopRegistrationController::class, 'store'])->middleware(['throttle:3,1', ProtectPublicForm::class]);
Route::post('/admin/logout', [AdminController::class, 'logout'])->middleware('auth:sanctum');
Route::middleware(['auth:sanctum', ResolveShop::class.':admin'])->prefix('admin')->group(function () {
    Route::get('/shop', [ShopRegistrationController::class, 'profile']);
    Route::patch('/shop', [ShopRegistrationController::class, 'update']);
    Route::get('/dashboard', [AdminSettingsController::class, 'dashboard']);
    Route::get('/profile', [AdminSettingsController::class, 'profile']);
    Route::patch('/profile', [AdminSettingsController::class, 'updateProfile']);
    Route::put('/password', [AdminSettingsController::class, 'password'])->middleware('throttle:5,1');
    Route::get('/invitations', [AdminSettingsController::class, 'invitations']);
    Route::post('/invitations', [AdminSettingsController::class, 'invite'])->middleware('throttle:5,1');
    Route::get('/bookings', [AdminController::class, 'index']);
    Route::patch('/bookings/{booking}', [AdminController::class, 'update']);
});

Route::get('/bookings/catalog', [BookingController::class, 'catalog'])->middleware(['throttle:public-availability', ResolveShop::class]);

Route::get(
    '/bookings/availability',
    [BookingController::class, 'availability']
)->middleware(['throttle:public-availability', ResolveShop::class]);

Route::post(
    '/bookings',
    [BookingController::class, 'store']
)->middleware(['throttle:public-booking', ProtectPublicForm::class, ResolveShop::class]);

Route::post(
    '/contact',
    [ContactController::class, 'store']
)->middleware(['throttle:public-contact', ProtectPublicForm::class, ResolveShop::class]);
