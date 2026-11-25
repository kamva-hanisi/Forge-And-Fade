<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingEmails;
use App\Services\BookingSchedule;
use App\Services\ShopCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    private function selectionRules(Request $request): array
    {
        return [
            'service_id' => ['required', 'integer', Rule::in(array_keys(ShopCatalog::services()))],
            'barber_id' => $request->input('barber_id') === 'any'
                ? ['required', Rule::in(ShopCatalog::barbers() ? ['any'] : [])]
                : ['required', 'integer', Rule::in(array_keys(ShopCatalog::barbers()))],
        ];
    }

    private function validateAppointment(Request $request, bool $creating): array
    {
        return $request->validate([
            ...$this->selectionRules($request),
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
        $shop = $request->attributes->get('shop');
        $paused = $shop->status !== 'active' && ! $request->attributes->has('shop_role');

        return response()->json([
            'services' => collect($paused ? [] : ShopCatalog::services())->map(fn ($service, $id) => ['id' => $id, ...$service])->values(),
            'barbers' => collect($paused ? [] : ShopCatalog::barbers())->map(fn ($barber, $id) => ['id' => $id, ...$barber])->values(),
            'booking_status' => $shop->status,
            'branding' => (object) ($shop->settings['branding'] ?? []),
            'timezone' => $shop->timezone,
            'hours' => $shop->settings['hours'] ?? config('booking.hours'),
            'shop' => $shop->only(['name', 'slug', 'address', 'phone']) + [
                'directions_url' => $shop->settings['directions_url'] ?? null,
            ],
        ])->header('Cache-Control', 'no-store');
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
        $date = $validated['booking_date'];
        $shop = $request->attributes->get('shop');
        $bookings = $this->scheduledBookings($shop->id, $barbers, $date, $date);

        return response()->json(['available_times' => $schedule->availableTimes($date, $duration, $barbers, $bookings->get($date, collect())),
            'timezone' => $shop->timezone]);
    }

    public function nextAvailable(Request $request, BookingSchedule $schedule)
    {
        $validated = $request->validate($this->selectionRules($request));
        $duration = ShopCatalog::services()[$validated['service_id']]['duration'];
        $barbers = $validated['barber_id'] === 'any' ? array_keys(ShopCatalog::barbers()) : [$validated['barber_id']];
        $shop = $request->attributes->get('shop');
        $today = now($shop->timezone)->startOfDay();
        $end = $today->copy()->addDays(6);
        $bookings = $this->scheduledBookings($shop->id, $barbers, $today->toDateString(), $end->toDateString());
        $slots = [];
        for ($day = $today; $day->lte($end) && count($slots) < 4; $day->addDay()) {
            $date = $day->toDateString();
            foreach ($schedule->availableTimes($date, $duration, $barbers, $bookings->get($date, collect()), 4 - count($slots)) as $time) {
                $slots[] = ['booking_date' => $date, 'booking_time' => $time];
            }
        }

        return response()->json(['slots' => $slots])->header('Cache-Control', 'no-store');
    }

    private function scheduledBookings(int $shopId, array $barbers, string $from, string $to): Collection
    {
        return Booking::where('shop_id', $shopId)->whereIn('barber_id', $barbers)
            ->whereBetween('booking_date', [$from, $to])->where('status', '!=', 'cancelled')->toBase()
            ->get(['booking_date', 'barber_id', 'booking_time', 'duration'])
            ->groupBy(['booking_date', 'barber_id']);
    }
}
