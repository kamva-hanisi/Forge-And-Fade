<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Str;

class ContactReplyEmail extends Mailable
{
    public function __construct(public string $shopName, public string $body) {}

    public function build(): static
    {
        return $this->subject('Reply from '.Str::squish($this->shopName))->view('mail.contact-reply');
    }
}
