<?php

namespace App\Services;

use App\Mail\BookingEmail;
use App\Models\Booking;
use App\Models\Shop;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class BookingEmails
{
    public static function ready(): bool
    {
        return (bool) config('booking_mail.enabled') && config('mail.default') === 'smtp'
            && filled(config('mail.mailers.smtp.host')) && filled(config('mail.mailers.smtp.username'))
            && filled(config('mail.mailers.smtp.password')) && filled(config('mail.from.address'));
    }

    public static function record(Booking $booking, string $kind): void
    {
        if (! config('booking_mail.enabled')) {
            return;
        }
        DB::table('booking_emails')->insertOrIgnore([
            'booking_id' => $booking->id, 'revision' => $booking->revision ?? 0, 'kind' => $kind,
            'status' => 'pending', 'attempts' => 0, 'available_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function reminders(): void
    {
        Booking::where('status', 'confirmed')->whereBetween('booking_date', [now()->subDays(2)->toDateString(), now()->addDays(3)->toDateString()])
            ->lazyById()->each(function (Booking $booking) {
                $start = CarbonImmutable::parse($booking->starts_at);
                if ($start->isFuture() && $start->lte(now()->addDay()) && $booking->created_at->lte($start->subDay())) {
                    self::record($booking, 'reminder');
                }
            });
    }

    public function deliver(int $id): string
    {
        $notice = DB::table('booking_emails')->find($id);
        $shopId = $notice ? Booking::whereKey($notice->booking_id)->value('shop_id') : null;
        if (! $shopId) {
            return 'skipped';
        }

        return DB::transaction(function () use ($id, $shopId) {
            $shop = Shop::whereKey($shopId)->lockForUpdate()->firstOrFail();
            $notice = DB::table('booking_emails')->where('id', $id)->lockForUpdate()->first();
            if (! $notice || ! in_array($notice->status, ['pending', 'failed'], true) || $notice->attempts >= 3
                || CarbonImmutable::parse($notice->available_at)->isFuture()) {
                return 'skipped';
            }
            $booking = Booking::whereKey($notice->booking_id)->lockForUpdate()->first();
            if (! $booking || (int) $booking->revision !== (int) $notice->revision
                || ($notice->kind === 'cancelled' ? $booking->status !== 'cancelled' : ($booking->status !== 'confirmed' || ! CarbonImmutable::parse($booking->starts_at)->isFuture()))) {
                DB::table('booking_emails')->where('id', $id)->update(['status' => 'skipped', 'updated_at' => now()]);

                return 'skipped';
            }
            try {
                Mail::to($booking->email)->send(new BookingEmail($booking, $shop, $notice->kind));
            } catch (\Throwable) {
                DB::table('booking_emails')->where('id', $id)->update(['status' => 'failed', 'attempts' => $notice->attempts + 1,
                    'available_at' => now()->addMinutes(5), 'updated_at' => now()]);

                return 'failed';
            }
            DB::table('booking_emails')->where('id', $id)->update(['status' => 'sent', 'attempts' => $notice->attempts + 1, 'sent_at' => now(), 'updated_at' => now()]);

            return 'sent';
        });
    }
}
