<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AdminRegistrationController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\ContactController;
use Illuminate\Support\Facades\Route;

Route::get('/admin/registration', [AdminRegistrationController::class, 'status']);
Route::post('/admin/register', [AdminRegistrationController::class, 'store'])->middleware('throttle:3,1');

Route::post('/admin/login', [AdminController::class, 'login'])->middleware('throttle:5,1');
Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    Route::post('/logout', [AdminController::class, 'logout']);
    Route::get('/bookings', [AdminController::class, 'index']);
    Route::patch('/bookings/{booking}', [AdminController::class, 'update']);
});

Route::get('/bookings/catalog', [BookingController::class, 'catalog']);

Route::get(
    '/bookings/availability',
    [BookingController::class, 'availability']
);

Route::post(
    '/bookings',
    [BookingController::class, 'store']
);

Route::post(
    '/contact',
    [ContactController::class, 'store']
);
