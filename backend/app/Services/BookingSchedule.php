<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingSchedule
{
    public function lockDay(int $barber, string $date): void
    {
        DB::table('booking_day_locks')->insertOrIgnore(['barber_id' => $barber, 'booking_date' => $date]);
        DB::table('booking_day_locks')->where('barber_id', $barber)->where('booking_date', $date)->lockForUpdate()->first();
    }

    private function minutes(string $time): int
    {
        return (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
    }

    public function bookings(int $barber, string $date, bool $lock = false)
    {
        $query = Booking::where('barber_id', $barber)->where('booking_date', $date)->where('status', '!=', 'cancelled');

        return ($lock ? $query->lockForUpdate() : $query)->get();
    }

    public function overlaps(string $time, int $duration, $bookings): bool
    {
        $start = $this->minutes($time);
        foreach ($bookings as $booking) {
            $existingStart = $this->minutes($booking->booking_time);
            if ($start < $existingStart + $booking->duration && $start + $duration > $existingStart) {
                return true;
            }
        }

        return false;
    }

    public function allowed(string $date, string $time, int $duration): bool
    {
        $start = Carbon::parse("{$date} {$time}", config('app.timezone'));
        $hours = config('booking.hours')[$start->dayOfWeekIso] ?? null;
        $minute = $this->minutes($time);
        if (! $hours || in_array($date, config('booking.closed_dates', []), true) || $start->lte(Carbon::now()) || $minute % config('booking.interval') !== 0
            || $minute < $this->minutes($hours[0]) || $minute + $duration > $this->minutes($hours[1])) {
            return false;
        }
        foreach (config('booking.breaks') as [$breakStart, $breakEnd]) {
            if ($minute < $this->minutes($breakEnd) && $minute + $duration > $this->minutes($breakStart)) {
                return false;
            }
        }

        return true;
    }

    public function validateTime(string $date, string $time, int $duration): void
    {
        if (! $this->allowed($date, $time, $duration)) {
            throw ValidationException::withMessages(['booking_time' => 'Choose a future available time within opening hours, outside breaks, with enough time for the full service.']);
        }
    }
}
