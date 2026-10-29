<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    private function validateAppointment(Request $request, bool $creating): array
    {
        return $request->validate([
            'service_id' => ['required', 'integer', Rule::in(array_keys(config('booking.services')))],
            'barber_id' => ['required', 'integer', Rule::in(array_keys(config('booking.barbers')))],
            'booking_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            ...($creating ? [
                'booking_time' => 'required|date_format:H:i',
                'customer_name' => 'required|string|max:100',
                'email' => 'required|email|max:150',
                'phone' => 'required|string|max:30',
                'notes' => 'nullable|string|max:1000',
            ] : []),
        ]);
    }

    public function catalog()
    {
        return response()->json([
            'services' => collect(config('booking.services'))->map(fn ($service, $id) => ['id' => $id, ...$service])->values(),
            'barbers' => collect(config('booking.barbers'))->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
            'timezone' => config('app.timezone'),
        ]);
    }

    public function store(Request $request, BookingSchedule $schedule)
    {
        $validated = $this->validateAppointment($request, true);
        $service = config('booking.services')[$validated['service_id']];

        return DB::transaction(function () use ($validated, $service, $schedule) {
            $schedule->lockDay($validated['barber_id'], $validated['booking_date']);
            $schedule->validateTime($validated['booking_date'], $validated['booking_time'], $service['duration']);
            if ($schedule->overlaps($validated['booking_time'], $service['duration'], $schedule->bookings($validated['barber_id'], $validated['booking_date'], true))) {
                return response()->json(['message' => 'That time is already booked. Please choose another time.'], 409);
            }
            $booking = Booking::create([
                ...$validated,
                'service_name' => $service['name'],
                'duration' => $service['duration'],
                'barber_name' => config('booking.barbers')[$validated['barber_id']],
                'status' => 'confirmed',
            ]);

            return response()->json(['message' => 'Your appointment has been booked successfully.', 'booking' => $booking], 201);
        }, 5);
    }

    public function availability(Request $request, BookingSchedule $schedule)
    {
        $validated = $this->validateAppointment($request, false);
        $duration = config('booking.services')[$validated['service_id']]['duration'];
        $bookings = $schedule->bookings($validated['barber_id'], $validated['booking_date']);
        $available = [];
        for ($minute = 0; $minute < 1440; $minute += config('booking.interval')) {
            $time = sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
            if ($schedule->allowed($validated['booking_date'], $time, $duration) && ! $schedule->overlaps($time, $duration, $bookings)) {
                $available[] = $time;
            }
        }

        return response()->json(['available_times' => $available, 'timezone' => config('app.timezone')]);
    }
}
