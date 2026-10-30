<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AdminMessageController;
use App\Http\Controllers\Api\AdminRecoveryController;
use App\Http\Controllers\Api\AdminRegistrationController;
use App\Http\Controllers\Api\AdminSettingsController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\BookingPaymentController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\CustomerBookingController;
use App\Http\Controllers\Api\ShopManagementController;
use App\Http\Controllers\Api\ShopRegistrationController;
use App\Http\Middleware\ProtectPublicForm;
use App\Http\Middleware\ResolveShop;
use Illuminate\Support\Facades\Route;

Route::get('/admin/registration', [AdminRegistrationController::class, 'status']);
Route::post('/admin/register', [AdminRegistrationController::class, 'store'])->middleware('throttle:3,1');

Route::post('/admin/login', [AdminController::class, 'login'])->middleware('throttle:5,1');
Route::post('/admin/forgot-password', [AdminRecoveryController::class, 'requestLink'])->middleware('throttle:admin-recovery');
Route::post('/admin/reset-password', [AdminRecoveryController::class, 'reset'])->middleware('throttle:5,1');
Route::post('/admin/register-shop', [ShopRegistrationController::class, 'store'])->middleware(['throttle:3,1', ProtectPublicForm::class]);
Route::post('/admin/logout', [AdminController::class, 'logout'])->middleware('auth:sanctum');
Route::middleware(['auth:sanctum', ResolveShop::class.':admin'])->prefix('admin')->group(function () {
    Route::get('/shop', [ShopRegistrationController::class, 'profile']);
    Route::get('/management', [ShopManagementController::class, 'index']);
    Route::put('/management/business-hours', [ShopManagementController::class, 'businessHours'])->middleware('throttle:30,1');
    Route::post('/management/services', [ShopManagementController::class, 'service']);
    Route::put('/management/services/{service}', [ShopManagementController::class, 'service'])->whereNumber('service');
    Route::post('/management/barbers', [ShopManagementController::class, 'barber']);
    Route::put('/management/barbers/{barber}', [ShopManagementController::class, 'barber'])->whereNumber('barber');
    Route::put('/management/barbers/{barber}/schedule', [ShopManagementController::class, 'schedule'])->whereNumber('barber');
    Route::put('/management/members/{member}', [ShopManagementController::class, 'member'])->whereNumber('member');
    Route::delete('/management/members/{member}', [ShopManagementController::class, 'member'])->whereNumber('member');
    Route::patch('/shop', [ShopRegistrationController::class, 'update']);
    Route::get('/dashboard', [AdminSettingsController::class, 'dashboard']);
    Route::get('/messages', [AdminMessageController::class, 'index']);
    Route::patch('/messages/{message}', [AdminMessageController::class, 'update'])->whereNumber('message')->middleware('throttle:30,1');
    Route::get('/profile', [AdminSettingsController::class, 'profile']);
    Route::patch('/profile', [AdminSettingsController::class, 'updateProfile']);
    Route::put('/password', [AdminSettingsController::class, 'password'])->middleware('throttle:5,1');
    Route::get('/invitations', [AdminSettingsController::class, 'invitations']);
    Route::post('/invitations', [AdminSettingsController::class, 'invite'])->middleware('throttle:5,1');
    Route::get('/bookings', [AdminController::class, 'index']);
    Route::post('/bookings/{booking}/payments', [BookingPaymentController::class, 'store'])->whereNumber('booking')->middleware('throttle:10,1');
    Route::post('/payments/{payment}/void', [BookingPaymentController::class, 'void'])->whereNumber('payment')->middleware('throttle:10,1');
    Route::post('/bookings', [AdminController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/bookings/catalog', [BookingController::class, 'catalog']);
    Route::get('/bookings/availability', [BookingController::class, 'availability'])->middleware('throttle:60,1');
    Route::patch('/bookings/{booking}', [AdminController::class, 'update']);
});

Route::get('/bookings/catalog', [BookingController::class, 'catalog'])->middleware(['throttle:public-availability', ResolveShop::class]);
Route::post('/bookings/lookup', [CustomerBookingController::class, 'lookup'])->middleware(['throttle:5,1', ResolveShop::class]);
Route::post('/bookings/cancel', [CustomerBookingController::class, 'cancel'])->middleware(['throttle:5,1', ResolveShop::class]);

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
