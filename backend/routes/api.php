<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\ContactController;

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