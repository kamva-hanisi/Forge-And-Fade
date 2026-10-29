<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AdminRegistrationController;
use App\Http\Controllers\Api\AdminSettingsController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Middleware\ProtectPublicForm;
use Illuminate\Support\Facades\Route;

Route::get('/admin/registration', [AdminRegistrationController::class, 'status']);
Route::post('/admin/register', [AdminRegistrationController::class, 'store'])->middleware('throttle:3,1');

Route::post('/admin/login', [AdminController::class, 'login'])->middleware('throttle:5,1');
Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminSettingsController::class, 'dashboard']);
    Route::get('/profile', [AdminSettingsController::class, 'profile']);
    Route::patch('/profile', [AdminSettingsController::class, 'updateProfile']);
    Route::put('/password', [AdminSettingsController::class, 'password'])->middleware('throttle:5,1');
    Route::get('/invitations', [AdminSettingsController::class, 'invitations']);
    Route::post('/invitations', [AdminSettingsController::class, 'invite'])->middleware('throttle:5,1');
    Route::post('/logout', [AdminController::class, 'logout']);
    Route::get('/bookings', [AdminController::class, 'index']);
    Route::patch('/bookings/{booking}', [AdminController::class, 'update']);
});

Route::get('/bookings/catalog', [BookingController::class, 'catalog'])->middleware('throttle:public-availability');

Route::get(
    '/bookings/availability',
    [BookingController::class, 'availability']
)->middleware('throttle:public-availability');

Route::post(
    '/bookings',
    [BookingController::class, 'store']
)->middleware(['throttle:public-booking', ProtectPublicForm::class]);

Route::post(
    '/contact',
    [ContactController::class, 'store']
)->middleware(['throttle:public-contact', ProtectPublicForm::class]);
