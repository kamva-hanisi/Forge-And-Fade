<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AdminMessageController;
use App\Http\Controllers\Api\AdminRecoveryController;
use App\Http\Controllers\Api\AdminRegistrationController;
use App\Http\Controllers\Api\AdminRolesController;
use App\Http\Controllers\Api\WebsiteInvitationController;
use App\Http\Controllers\Api\AdminSettingsController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\BookingPaymentController;
use App\Http\Controllers\Api\PayfastController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\CustomerBookingController;
use App\Http\Controllers\Api\PlatformController;
use App\Http\Controllers\Api\ShopLocationController;
use App\Http\Controllers\Api\ShopManagementController;
use App\Http\Controllers\Api\ShopRegistrationController;
use App\Http\Middleware\ProtectPublicForm;
use App\Http\Middleware\ResolveShop;
use App\Models\Shop;
use App\Services\BookingEmails;
use App\Services\ShopMedia;
use Illuminate\Support\Facades\Route;

// Numeric throttles share a bucket by default. Prefix each independent operation.
Route::get('/admin/registration', [AdminRegistrationController::class, 'status']);
Route::post('/admin/register', [AdminRegistrationController::class, 'store'])->middleware('throttle:3,1,staff-registration:');
Route::post('/admin/register-super-admin', [WebsiteInvitationController::class, 'accept'])->middleware('throttle:3,1,super-admin-registration:');

Route::post('/admin/login', [AdminController::class, 'login'])->middleware('throttle:5,1,admin-sign-in:');
Route::post('/admin/forgot-password', [AdminRecoveryController::class, 'requestLink'])->middleware('throttle:admin-recovery');
Route::post('/admin/reset-password', [AdminRecoveryController::class, 'reset'])->middleware('throttle:5,1,admin-password-reset:');
Route::post('/admin/register-shop', [ShopRegistrationController::class, 'store'])->middleware(['throttle:3,1,business-registration:', ProtectPublicForm::class]);
Route::post('/admin/logout', [AdminController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/platform/overview', [PlatformController::class, 'index'])->middleware(['auth:sanctum', 'throttle:30,1,platform-overview:']);
Route::post('/platform/shops', [ShopRegistrationController::class, 'addBusiness'])->middleware(['auth:sanctum', 'throttle:10,1,add-business:']);
Route::put('/platform/shops/{shop}/public-default', [PlatformController::class, 'selectPublicBusiness'])->whereNumber('shop')->middleware(['auth:sanctum', 'throttle:20,1,select-public-business:']);
Route::put('/platform/shops/{shop}/status', [PlatformController::class, 'status'])->whereNumber('shop')->middleware(['auth:sanctum', 'throttle:10,1,business-status:']);
Route::delete('/platform/shops/{shop}', [PlatformController::class, 'destroy'])->whereNumber('shop')->middleware(['auth:sanctum', 'throttle:10,1,delete-business:']);
Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    Route::get('/session', [AdminController::class, 'session']);
    Route::get('/profile', [AdminSettingsController::class, 'profile']);
    Route::patch('/profile', [AdminSettingsController::class, 'updateProfile']);
    Route::put('/password', [AdminSettingsController::class, 'password'])->middleware('throttle:5,1,change-password:');
    Route::get('/roles', [AdminRolesController::class, 'index']);
    Route::get('/roles/account', [AdminRolesController::class, 'account'])->middleware('throttle:30,1,role-account-search:');
    Route::post('/roles/invitations', [WebsiteInvitationController::class, 'store'])->middleware('throttle:10,1,website-invite:');
    Route::delete('/roles/invitations/{invitation}', [WebsiteInvitationController::class, 'revoke'])->whereNumber('invitation')->middleware('throttle:10,1,revoke-website-invite:');
    Route::put('/roles/accounts/{account}', [AdminRolesController::class, 'update'])->whereNumber('account')->middleware('throttle:20,1,update-website-role:');
});
Route::middleware(['auth:sanctum', ResolveShop::class.':admin'])->prefix('admin')->group(function () {
    Route::get('/shop', [ShopRegistrationController::class, 'profile']);
    Route::get('/management', [ShopManagementController::class, 'index']);
    Route::get('/management/branding', [ShopManagementController::class, 'brandingSettings']);
    Route::put('/management/branding', [ShopManagementController::class, 'branding'])->middleware('throttle:30,1,branding-accent:');
    Route::post('/management/branding/images', [ShopManagementController::class, 'publishBranding'])->middleware('throttle:20,1,publish-branding:');
    Route::post('/management/media', [ShopManagementController::class, 'media'])->middleware('throttle:20,1,manage-business-media:');
    Route::get('/management/media/{image}', [ShopManagementController::class, 'previewMedia'])
        ->where('image', '[a-f0-9-]{36}\.(jpg|png|webp)')->middleware('throttle:120,1,admin-media-preview:');
    Route::get('/management/business-hours', [ShopManagementController::class, 'openingHours']);
    Route::put('/management/business-hours', [ShopManagementController::class, 'businessHours'])->middleware('throttle:30,1,edit-business-hours:');
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
    Route::post('/messages/{message}/reply', [AdminMessageController::class, 'reply'])->whereNumber('message')->middleware('throttle:10,1,contact-reply:');
    Route::patch('/messages/{message}', [AdminMessageController::class, 'update'])->whereNumber('message')->middleware('throttle:30,1,contact-status:');
    Route::get('/invitations', [AdminSettingsController::class, 'invitations']);
    Route::post('/invitations', [AdminSettingsController::class, 'invite'])->middleware('throttle:5,1,staff-invitation:');
    Route::get('/bookings', [AdminController::class, 'index']);
    Route::post('/bookings/{booking}/emails/{kind}/retry', [AdminController::class, 'retryEmail'])
        ->whereNumber('booking')->whereIn('kind', BookingEmails::DASHBOARD_KINDS)->middleware('throttle:10,1,retry-booking-email:');
    Route::post('/bookings/{booking}/payments', [BookingPaymentController::class, 'store'])->whereNumber('booking')->middleware('throttle:10,1,record-booking-payment:');
    Route::post('/payments/{payment}/void', [BookingPaymentController::class, 'void'])->whereNumber('payment')->middleware('throttle:10,1,void-booking-payment:');
    Route::post('/bookings', [AdminController::class, 'store'])->middleware('throttle:10,1,staff-booking:');
    Route::get('/bookings/catalog', [BookingController::class, 'catalog']);
    Route::get('/bookings/availability', [BookingController::class, 'availability'])->middleware('throttle:60,1,staff-availability:');
    Route::patch('/bookings/{booking}', [AdminController::class, 'update']);
});

Route::get('/locations', [ShopLocationController::class, 'index'])->middleware('throttle:public-availability');
Route::get('/bookings/catalog', [BookingController::class, 'catalog'])->middleware(['throttle:public-availability', ResolveShop::class]);
Route::get('/bookings/next-available', [BookingController::class, 'nextAvailable'])->middleware(['throttle:public-availability', ResolveShop::class]);
Route::get('/shop-media/{shop:slug}/{image}', fn (Shop $shop, string $image) => ShopMedia::show($shop, $image))
    ->where('image', '[a-f0-9-]{36}\.(jpg|png|webp)')->middleware('throttle:120,1,public-media:');
Route::post('/bookings/lookup', [CustomerBookingController::class, 'lookup'])->middleware(['throttle:5,1,customer-booking-access:', ResolveShop::class]);
Route::post('/bookings/cancel', [CustomerBookingController::class, 'cancel'])->middleware(['throttle:5,1,customer-booking-access:', ResolveShop::class]);

Route::get(
    '/bookings/availability',
    [BookingController::class, 'availability']
)->middleware(['throttle:public-availability', ResolveShop::class]);

Route::post(
    '/bookings',
    [BookingController::class, 'store']
)->middleware(['throttle:public-booking', ProtectPublicForm::class, ResolveShop::class]);

Route::post(
    '/bookings/checkout',
    [BookingController::class, 'checkout']
)->middleware(['throttle:public-booking', ProtectPublicForm::class, ResolveShop::class]);

Route::get('/payfast/return/{reference}', [PayfastController::class, 'redirectToBooking'])
    ->where('reference', 'PF-[A-Za-z0-9]{32}');
Route::get('/payfast/status/{reference}', [PayfastController::class, 'status'])
    ->where('reference', 'PF-[A-Za-z0-9]{32}')->middleware('throttle:20,1,payfast-status:');
Route::post('/payfast/notify', [PayfastController::class, 'notify']);

Route::post(
    '/contact',
    [ContactController::class, 'store']
)->middleware(['throttle:public-contact', ProtectPublicForm::class, ResolveShop::class]);
