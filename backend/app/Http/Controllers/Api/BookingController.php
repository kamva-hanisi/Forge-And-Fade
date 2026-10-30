<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingEmails;
use App\Services\BookingSchedule;
use App\Services\ShopCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    private function validateAppointment(Request $request, bool $creating): array
    {
        return $request->validate([
            'service_id' => ['required', 'integer', Rule::in(array_keys(ShopCatalog::services()))],
            'barber_id' => $request->input('barber_id') === 'any'
                ? ['required', Rule::in(ShopCatalog::barbers() ? ['any'] : [])]
                : ['required', 'integer', Rule::in(array_keys(ShopCatalog::barbers()))],
            'booking_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            ...($creating ? [
                'booking_time' => 'required|date_format:H:i',
                'customer_name' => 'required|string|max:100',
                'email' => 'required|email|max:150',
                'phone' => 'required|string|max:30',
                'notes' => 'nullable|string|max:1000',
                'quoted_price' => 'sometimes|numeric|min:0|max:99999999.99',
            ] : []),
        ]);
    }

    public function catalog(Request $request)
    {
        return response()->json([
            'services' => collect(ShopCatalog::services())->map(fn ($service, $id) => ['id' => $id, ...$service])->values(),
            'barbers' => collect(ShopCatalog::barbers())->map(fn ($barber, $id) => ['id' => $id, ...$barber])->values(),
            'timezone' => $request->attributes->get('shop')->timezone,
            'shop' => $request->attributes->get('shop')->only(['name', 'slug', 'address', 'phone']),
        ]);
    }

    public function store(Request $request, BookingSchedule $schedule)
    {
        return DB::transaction(function () use ($schedule, $request) {
            ShopCatalog::lock($request);
            $validated = $this->validateAppointment($request, true);
            $service = ShopCatalog::services()[$validated['service_id']];
            if (isset($validated['quoted_price']) && (int) round((float) $validated['quoted_price'] * 100) !== (int) round($service['price'] * 100)) {
                throw ValidationException::withMessages(['quoted_price' => 'The service price has changed. Review the updated price before booking.']);
            }
            unset($validated['quoted_price']);

            if ($validated['barber_id'] === 'any') {
                $assigned = null;
                $barbers = array_keys(ShopCatalog::barbers());
                sort($barbers, SORT_NUMERIC);
                foreach ($barbers as $barber) {
                    $schedule->lockDay($barber, $validated['booking_date']);
                    if ($schedule->allowed($validated['booking_date'], $validated['booking_time'], $service['duration'], $barber)
                        && ! $schedule->overlaps($validated['booking_time'], $service['duration'], $schedule->bookings($barber, $validated['booking_date'], true))) {
                        $assigned = $barber;
                        break;
                    }
                }
                if ($assigned === null) {
                    return response()->json(['message' => 'No barber is available at that time. Please choose another time.'], 409);
                }
                $validated['barber_id'] = $assigned;
            }
            $schedule->lockDay($validated['barber_id'], $validated['booking_date']);
            $schedule->validateTime($validated['booking_date'], $validated['booking_time'], $service['duration'], $validated['barber_id']);
            if ($schedule->overlaps($validated['booking_time'], $service['duration'], $schedule->bookings($validated['barber_id'], $validated['booking_date'], true))) {
                return response()->json(['message' => 'That time is already booked. Please choose another time.'], 409);
            }
            $booking = Booking::create([
                ...$validated,
                'service_name' => $service['name'],
                'duration' => $service['duration'],
                'barber_name' => ShopCatalog::barbers()[$validated['barber_id']]['name'],
                'status' => 'confirmed',
                'price' => $service['price'],
                'currency' => 'ZAR',
                'shop_id' => $request->attributes->get('shop')->id,
            ]);

            return response()->json(['message' => 'Your appointment has been booked successfully.', 'booking' => $booking->fresh(),
                'email_notification' => BookingEmails::ready() ? 'pending' : 'not_configured',
                'shop' => $request->attributes->get('shop')->only(['name', 'slug', 'address', 'phone'])], 201)
                ->header('Cache-Control', 'no-store');
        }, 5);
    }

    public function availability(Request $request, BookingSchedule $schedule)
    {
        $validated = $this->validateAppointment($request, false);
        $duration = ShopCatalog::services()[$validated['service_id']]['duration'];
        $barbers = $validated['barber_id'] === 'any' ? array_keys(ShopCatalog::barbers()) : [$validated['barber_id']];
        $bookings = [];
        foreach ($barbers as $barber) {
            $bookings[$barber] = $schedule->bookings($barber, $validated['booking_date']);
        }
        $available = [];
        for ($minute = 0; $minute < 1440; $minute += config('booking.interval')) {
            $time = sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
            foreach ($barbers as $barber) {
                if ($schedule->allowed($validated['booking_date'], $time, $duration, $barber) && ! $schedule->overlaps($time, $duration, $bookings[$barber])) {
                    $available[] = $time;
                    break;
                }
            }
        }

        return response()->json(['available_times' => $available, 'timezone' => $request->attributes->get('shop')->timezone]);
    }
}
