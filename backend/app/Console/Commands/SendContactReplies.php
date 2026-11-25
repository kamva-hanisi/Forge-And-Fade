<?php

namespace App\Console\Commands;

use App\Mail\ContactReplyEmail;
use App\Models\ContactReply;
use App\Models\Shop;
use App\Services\OutgoingMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendContactReplies extends Command
{
    protected $signature = 'contact:send-replies';

    protected $description = 'Send queued replies to contact enquiries';

    public function handle(): int
    {
        if (! OutgoingMail::ready()) {
            $this->warn('Reply delivery is unavailable. Check the mail configuration.');

            return self::FAILURE;
        }

        $counts = ['sent' => 0, 'failed' => 0];
        $ids = ContactReply::where('status', 'pending')
            ->whereHas('enquiry', fn ($query) => $query->whereIn('shop_id', Shop::where('status', 'active')->select('id')))
            ->orderBy('id')->limit(20)->pluck('id');
        foreach ($ids as $id) {
            $status = DB::transaction(function () use ($id) {
                $reply = ContactReply::whereKey($id)->lockForUpdate()->first();
                if (! $reply || $reply->status !== 'pending') {
                    return null;
                }
                $enquiry = $reply->enquiry;
                $shop = $enquiry ? Shop::whereKey($enquiry->shop_id)->where('status', 'active')->first() : null;
                if (! $shop) {
                    return null;
                }
                try {
                    Mail::to($enquiry->email)->send(new ContactReplyEmail($shop->name, $reply->body));
                } catch (\Throwable) {
                    $reply->update(['status' => 'failed']);

                    return 'failed';
                }
                $reply->update(['status' => 'sent', 'sent_at' => now()]);

                return 'sent';
            });
            if ($status !== null) {
                $counts[$status]++;
            }
        }

        $this->info("Contact replies sent: {$counts['sent']}; failed: {$counts['failed']}.");

        return $counts['failed'] ? self::FAILURE : self::SUCCESS;
    }
}
