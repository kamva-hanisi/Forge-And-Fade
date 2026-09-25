<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'service_id' => 'required|integer',
            'service_name' => 'required|string|max:100',

            'barber_id' => 'required|integer',
            'barber_name' => 'required|string|max:100',

            'booking_date' => 'required|date|after_or_equal:today',
            'booking_time' => 'required|date_format:H:i',

            'duration' => 'required|integer|min:1',

            'customer_name' => 'required|string|max:100',
            'email' => 'required|email|max:150',
            'phone' => 'required|string|max:30',

            'notes' => 'nullable|string|max:1000',
        ]);

        $alreadyBooked = Booking::where(
                'barber_id',
                $validated['barber_id']
            )
            ->whereDate(
                'booking_date',
                $validated['booking_date']
            )
            ->where(
                'booking_time',
                $validated['booking_time']
            )
            ->where(
                'status',
                '!=',
                'cancelled'
            )
            ->exists();

        if ($alreadyBooked) {
            return response()->json([
                'message' =>
                    'That time is already booked. Please choose another time.',
            ], 409);
        }

        $booking = Booking::create([
            ...$validated,
            'status' => 'confirmed',
        ]);

        return response()->json([
            'message' =>
                'Your appointment has been booked successfully.',

            'booking' => $booking,
        ], 201);
    }

    public function availability(Request $request)
    {
        $validated = $request->validate([
            'barber_id' => 'required|integer',
            'booking_date' => 'required|date',
        ]);

        $bookedTimes = Booking::where(
                'barber_id',
                $validated['barber_id']
            )
            ->whereDate(
                'booking_date',
                $validated['booking_date']
            )
            ->where(
                'status',
                '!=',
                'cancelled'
            )
            ->pluck('booking_time')
            ->map(function ($time) {
                return substr($time, 0, 5);
            })
            ->values();

        return response()->json([
            'booked_times' => $bookedTimes,
        ]);
    }
}