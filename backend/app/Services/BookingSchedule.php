<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingSchedule
{
    public function lockDay(int $barber, string $date): void
    {
        $shop = request()->attributes->get('shop')->id;
        DB::table('shop_booking_day_locks')->insertOrIgnore(['shop_id' => $shop, 'barber_id' => $barber, 'booking_date' => $date]);
        DB::table('shop_booking_day_locks')->where('shop_id', $shop)->where('barber_id', $barber)->where('booking_date', $date)->lockForUpdate()->first();
    }

    private function minutes(string $time): int
    {
        return (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
    }

    public function bookings(int $barber, string $date, bool $lock = false)
    {
        $query = Booking::where('shop_id', request()->attributes->get('shop')->id)->where('barber_id', $barber)->where('booking_date', $date)->where('status', '!=', 'cancelled');

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

    public function availableTimes(string $date, int $duration, array $barbers, Collection $bookings, int $limit = PHP_INT_MAX): array
    {
        $shop = request()->attributes->get('shop');
        $settings = $shop->settings ?? [];
        $weekday = Carbon::parse($date, $shop->timezone)->dayOfWeekIso;
        $hours = ($settings['hours'] ?? config('booking.hours'))[$weekday] ?? null;
        if (! $hours || in_array($date, $settings['closed_dates'] ?? config('booking.closed_dates', []), true)) {
            return [];
        }
        $barbers = array_filter($barbers, function ($barber) use ($settings, $weekday, $date) {
            $staff = $settings['staff_schedules'][$barber] ?? null;

            return $staff === null || (! empty($staff['hours'][$weekday]) && ! in_array($date, $staff['closed_dates'] ?? [], true));
        });
        $interval = config('booking.interval');
        $opening = $this->minutes($hours[0]);
        $now = now($shop->timezone);
        if ($date === $now->toDateString()) {
            $opening = max($opening, $this->minutes($now->format('H:i')) + 1);
        }
        $last = $this->minutes($hours[1]) - $duration;
        $times = [];
        for ($minute = (int) ceil($opening / $interval) * $interval; $barbers && $minute <= $last; $minute += $interval) {
            $time = sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
            foreach ($barbers as $barber) {
                if ($this->allowed($date, $time, $duration, $barber) && ! $this->overlaps($time, $duration, $bookings->get($barber, []))) {
                    $times[] = $time;
                    break;
                }
            }
            if (count($times) >= $limit) {
                break;
            }
        }

        return $times;
    }

    public function allowed(string $date, string $time, int $duration, ?int $barber = null, bool $allowStarted = false): bool
    {
        $shop = request()->attributes->get('shop');
        $start = Carbon::parse("{$date} {$time}", $shop?->timezone ?? config('app.timezone'));
        $settings = $shop?->settings ?? [];
        $barber ??= request()->integer('barber_id');
        $staff = $settings['staff_schedules'][$barber] ?? null;
        $hours = ($settings['hours'] ?? config('booking.hours'))[$start->dayOfWeekIso] ?? null;
        $minute = $this->minutes($time);
        if (! $hours || in_array($date, $settings['closed_dates'] ?? config('booking.closed_dates', []), true) || (! $allowStarted && $start->lte(Carbon::now())) || $minute % config('booking.interval') !== 0
            || $minute < $this->minutes($hours[0]) || $minute + $duration > $this->minutes($hours[1])) {
            return false;
        }
        foreach ($settings['breaks'] ?? config('booking.breaks') as [$breakStart, $breakEnd]) {
            if ($minute < $this->minutes($breakEnd) && $minute + $duration > $this->minutes($breakStart)) {
                return false;
            }
        }
        if ($staff !== null) {
            $staffHours = $staff['hours'][$start->dayOfWeekIso] ?? null;
            if (! $staffHours || in_array($date, $staff['closed_dates'] ?? [], true)
                || $minute < $this->minutes($staffHours[0]) || $minute + $duration > $this->minutes($staffHours[1])) {
                return false;
            }
            foreach ($staff['breaks'] ?? [] as [$breakStart, $breakEnd]) {
                if ($minute < $this->minutes($breakEnd) && $minute + $duration > $this->minutes($breakStart)) {
                    return false;
                }
            }
        }

        return true;
    }

    public function validateTime(string $date, string $time, int $duration, ?int $barber = null): void
    {
        if (! $this->allowed($date, $time, $duration, $barber)) {
            throw ValidationException::withMessages(['booking_time' => 'Choose a future available time within opening hours, outside breaks, with enough time for the full service.']);
        }
    }
}
