<?php

namespace App\Services;

class OutgoingMail
{
    public static function ready(): bool
    {
        if (config('mail.default') !== 'smtp' || blank(config('mail.from.address'))) {
            return false;
        }

        $smtp = config('mail.mailers.smtp');
        if (config('booking_mail.local_mailpit')) {
            return app()->environment('local') && $smtp['host'] === '127.0.0.1'
                && (int) $smtp['port'] === 1025 && blank($smtp['url'])
                && in_array($smtp['scheme'], [null, 'smtp'], true)
                && blank($smtp['username']) && blank($smtp['password']);
        }

        return filled($smtp['host']) && filled($smtp['username']) && filled($smtp['password']);
    }
}
