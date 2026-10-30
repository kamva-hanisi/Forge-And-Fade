<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingSchedule;
use App\Services\ShopCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerBookingController extends Controller
{
    public function lookup(Request $request)
    {
        return $this->respond($this->find($request));
    }

    public function cancel(Request $request, BookingSchedule $schedule)
    {
        $request->validate(['revision' => 'required|integer|min:0']);

        return DB::transaction(function () use ($request, $schedule) {
            ShopCatalog::lock($request);
            $booking = $this->find($request, true);
            if ($booking->status === 'cancelled') {
                return $this->respond($booking);
            }
            abort_if($booking->revision !== $request->integer('revision'), 409, 'This appointment changed. Look it up again before cancelling.');
            abort_unless(CarbonImmutable::parse($booking->starts_at)->isFuture(), 409, 'This appointment has already started. Contact the shop.');
            $schedule->lockDay($booking->barber_id, $booking->booking_date->toDateString());
            $booking->status = 'cancelled';
            $booking->revision++;
            $booking->save();

            return $this->respond($booking);
        }, 5);
    }

    private function find(Request $request, bool $lock = false): Booking
    {
        $input = $request->validate([
            'reference' => 'required|string|regex:/^FF-[A-Z0-9]{16}$/',
            'email' => 'required|email|max:150',
        ]);
        $query = Booking::where('shop_id', $request->attributes->get('shop')->id)
            ->where('reference', $input['reference']);
        $booking = ($lock ? $query->lockForUpdate() : $query)->first();
        abort_unless($booking && hash_equals(strtolower($booking->email), strtolower(trim($input['email']))), 404, 'No matching booking found. Check your reference, email and shop.');

        return $booking;
    }

    private function respond(Booking $booking)
    {
        return response()->json(['booking' => [...$booking->only([
            'reference', 'service_name', 'barber_name', 'booking_date', 'booking_time',
            'duration', 'price', 'currency', 'timezone', 'starts_at', 'ends_at', 'status', 'revision',
        ]), 'booking_date' => $booking->booking_date->toDateString()], 'can_cancel' => $booking->status === 'confirmed' && CarbonImmutable::parse($booking->starts_at)->isFuture()])
            ->header('Cache-Control', 'no-store, private');
    }
}
