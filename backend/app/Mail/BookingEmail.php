<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\Shop;
use Illuminate\Mail\Mailable;

class BookingEmail extends Mailable
{
    public function __construct(public Booking $booking, public Shop $shop, public string $kind) {}

    public function build(): static
    {
        $title = match ($this->kind) {
            'reminder' => 'Appointment reminder',
            'cancelled' => 'Appointment cancelled',
            'changed' => 'Appointment updated',
            default => 'Appointment confirmed',
        };

        return $this->subject($this->shop->name.' — '.$title)->view('mail.booking', [
            'title' => $title,
            'manageUrl' => rtrim(config('admin.frontend_url'), '/').'/#/shops/'.rawurlencode($this->shop->slug).'/manage',
        ]);
    }
}
