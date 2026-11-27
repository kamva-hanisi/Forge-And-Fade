<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class WebsiteInvitationEmail extends Mailable
{
    public function __construct(public string $link) {}

    public function build(): static
    {
        return $this->subject('Your Super Admin invitation')->text('mail.website-invitation');
    }
}
