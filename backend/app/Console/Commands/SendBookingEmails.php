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
            $this->warn('Booking email is disabled or authenticated SMTP is not configured. No messages sent.');

            return self::FAILURE;
        }
        $emails->reminders();
        $counts = ['sent' => 0, 'failed' => 0, 'skipped' => 0];
        $ids = DB::table('booking_emails')->whereIn('status', ['pending', 'failed'])->where('attempts', '<', 3)
            ->where('available_at', '<=', now())->orderBy('id')->limit(100)->pluck('id');
        foreach ($ids as $id) {
            $counts[$emails->deliver($id)]++;
        }
        $this->info("Sent: {$counts['sent']}; failed: {$counts['failed']}; skipped: {$counts['skipped']}.");
        $exhausted = DB::table('booking_emails')->where('status', 'failed')->where('attempts', '>=', 3)->count();
        if ($exhausted) {
            $this->warn("{$exhausted} messages exhausted their retries. Investigate delivery before manually retrying.");
        }

        return $counts['failed'] || $exhausted ? self::FAILURE : self::SUCCESS;
    }
}
