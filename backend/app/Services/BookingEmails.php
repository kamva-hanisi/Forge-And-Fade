<?php

namespace App\Services;

use App\Mail\BookingEmail;
use App\Models\Booking;
use App\Models\Shop;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class BookingEmails
{
    public const DASHBOARD_KINDS = ['confirmation', 'cancelled', 'reminder'];

    public const MAX_ATTEMPTS = 3;

    public static function filterFailed(Builder $bookings): void
    {
        $bookings->whereExists(function (QueryBuilder $email) {
            $email->selectRaw('1')->from('booking_emails as notice')
                ->whereColumn('notice.booking_id', 'bookings.id')
                ->whereIn('notice.kind', self::DASHBOARD_KINDS)
                ->where('notice.status', 'failed')
                ->whereNotExists(fn (QueryBuilder $newer) => $newer->selectRaw('1')->from('booking_emails as newer')
                    ->whereColumn('newer.booking_id', 'notice.booking_id')
                    ->whereColumn('newer.kind', 'notice.kind')
                    ->whereColumn('newer.revision', '>', 'notice.revision'));
        });
    }

    public static function latestForBookings(Collection $bookings, bool $shopActive): Collection
    {
        $bookingsById = $bookings->keyBy('id');
        $deliveryReady = self::ready();
        $latest = DB::table('booking_emails')->select('booking_id', 'kind')
            ->selectRaw('MAX(revision) as revision')
            ->whereIn('booking_id', $bookingsById->keys()->all())
            ->whereIn('kind', self::DASHBOARD_KINDS)
            ->groupBy('booking_id', 'kind');

        return DB::table('booking_emails as notice')
            ->joinSub($latest, 'latest', fn (JoinClause $join) => $join
                ->on('notice.booking_id', 'latest.booking_id')
                ->on('notice.kind', 'latest.kind')
                ->on('notice.revision', 'latest.revision'))
            ->select('notice.booking_id', 'notice.kind', 'notice.status', 'notice.attempts', 'notice.revision', 'notice.sent_at')
            ->orderBy('notice.kind')
            ->get()
            ->groupBy('booking_id')
            ->map(fn (Collection $notices) => $notices->map(function ($notice) use ($bookingsById, $shopActive, $deliveryReady) {
                $canRetry = $shopActive && $notice->status === 'failed'
                    && self::matchesBooking($bookingsById->get($notice->booking_id), $notice);

                return [
                    'kind' => $notice->kind,
                    'status' => $notice->status,
                    'attempts' => (int) $notice->attempts,
                    'revision' => (int) $notice->revision,
                    'sent_at' => $notice->sent_at
                        ? CarbonImmutable::parse($notice->sent_at, config('app.timezone'))->toIso8601String()
                        : null,
                    'can_retry' => $canRetry,
                    'automatic_retry_scheduled' => $deliveryReady && $canRetry && $notice->attempts < self::MAX_ATTEMPTS,
                ];
            }));
    }

    public static function retryFailed(Booking $booking, string $kind, int $revision): int
    {
        $notice = DB::table('booking_emails')->where('booking_id', $booking->id)
            ->where('kind', $kind)->where('revision', $revision)->lockForUpdate()->first();
        abort_unless($notice && $notice->status === 'failed' && self::matchesBooking($booking, $notice),
            409, 'This email can no longer be retried. Refresh the appointment to see its latest status.');
        abort_unless(self::ready(), 503, 'Booking email delivery is unavailable. Check the mail configuration and try again.');

        DB::table('booking_emails')->where('id', $notice->id)->update([
            'status' => 'pending', 'attempts' => 0, 'available_at' => now(), 'sent_at' => null, 'updated_at' => now(),
        ]);

        return (int) $notice->id;
    }

    private static function matchesBooking(Booking $booking, object $notice): bool
    {
        return (int) $booking->revision === (int) $notice->revision
            && ($notice->kind === 'cancelled'
                ? $booking->status === 'cancelled'
                : $booking->status === 'confirmed' && CarbonImmutable::parse($booking->starts_at)->isFuture());
    }

    public static function ready(): bool
    {
        return (bool) config('booking_mail.enabled') && OutgoingMail::ready();
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
        Booking::whereIn('shop_id', Shop::where('status', 'active')->select('id'))->where('status', 'confirmed')->whereBetween('booking_date', [now()->subDays(2)->toDateString(), now()->addDays(3)->toDateString()])
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
            if (! $notice || ! in_array($notice->status, ['pending', 'failed'], true) || $notice->attempts >= self::MAX_ATTEMPTS
                || CarbonImmutable::parse($notice->available_at)->isFuture()) {
                return 'skipped';
            }
            if ($shop->status !== 'active' && $notice->kind !== 'cancelled') {
                return 'skipped';
            }
            $booking = Booking::whereKey($notice->booking_id)->lockForUpdate()->first();
            if (! $booking || ! self::matchesBooking($booking, $notice)) {
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
