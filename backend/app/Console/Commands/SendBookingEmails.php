<?php

namespace App\Console\Commands;

use App\Services\BookingEmails;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendBookingEmails extends Command
{
    protected $signature = 'bookings:notify';

    protected $description = 'Send pending booking emails and due 24-hour reminders';

    public function handle(BookingEmails $emails): int
    {
        if (! BookingEmails::ready()) {
            $this->warn('Booking email is disabled or SMTP is not configured. No messages sent.');

            return self::FAILURE;
        }
        $emails->reminders();
        $counts = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        $ids = DB::table('booking_emails')->whereIn('status', ['pending', 'failed'])->where('attempts', '<', BookingEmails::MAX_ATTEMPTS)
            ->where(function ($query) {
                $query->where('kind', 'cancelled')->orWhereExists(function ($query) {
                    $query->selectRaw('1')->from('bookings')->join('shops', 'shops.id', '=', 'bookings.shop_id')
                        ->whereColumn('bookings.id', 'booking_emails.booking_id')->where('shops.status', 'active');
                });
            })
            ->where('available_at', '<=', now())->orderBy('id')->limit(100)->pluck('id');
        foreach ($ids as $id) {
            $counts[$emails->deliver($id)]++;
        }
        $this->info("Sent: {$counts['sent']}; failed: {$counts['failed']}; skipped: {$counts['skipped']}.");
        $exhausted = DB::table('booking_emails')->where('status', 'failed')->where('attempts', '>=', BookingEmails::MAX_ATTEMPTS)->count();
        if ($exhausted) {
            $this->warn("{$exhausted} messages exhausted their retries. Investigate delivery before manually retrying.");
        }

        return $counts['failed'] || $exhausted ? self::FAILURE : self::SUCCESS;
    }
}
