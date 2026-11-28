<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Shop;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PayfastCheckout
{
    private const SANDBOX = 'https://sandbox.payfast.co.za';

    public function merchant(Shop $shop): ?array
    {
        $merchant = config('payfast.sandbox_merchants')[$shop->slug] ?? null;
        if (! app()->environment(['local', 'testing']) || ! is_array($merchant)) {
            return null;
        }
        foreach (['merchant_id', 'merchant_key', 'passphrase'] as $field) {
            if (! is_string($merchant[$field] ?? null) || trim($merchant[$field]) === '') {
                return null;
            }
        }

        return preg_match('/^\d{8}$/D', $merchant['merchant_id']) ? $merchant : null;
    }

    public function initialize(Booking $booking, array $merchant): array
    {
        $callback = rtrim(config('payfast.callback_base_url'), '/').'/api/payfast/return/'.$booking->payment_reference;
        $fields = [
            'merchant_id' => $merchant['merchant_id'],
            'merchant_key' => $merchant['merchant_key'],
            'return_url' => $callback,
            'cancel_url' => $callback,
        ];
        if ($notify = config('payfast.notify_url')) {
            $fields['notify_url'] = $notify;
        }
        $fields += [
            'm_payment_id' => $booking->payment_reference,
            'amount' => $booking->price,
            'item_name' => $booking->service_name,
        ];
        $fields['signature'] = $this->signature(http_build_query($fields), $merchant['passphrase']);

        return ['url' => self::SANDBOX.'/eng/process', 'fields' => $fields];
    }

    public function notification(string $body): array
    {
        abort_if(strlen($body) > 16384, 413);
        $fields = [];
        foreach (explode('&', $body) as $pair) {
            $parts = explode('=', $pair, 2);
            abort_unless(count($parts) === 2 && preg_match('/^[a-zA-Z0-9_]+$/D', $parts[0]) && ! isset($fields[$parts[0]]), 400);
            $fields[$parts[0]] = urldecode($parts[1]);
        }
        abort_unless(array_key_last($fields) === 'signature' && preg_match('/^[a-f0-9]{32}$/D', $fields['signature']), 400);

        return $fields;
    }

    public function verify(Booking $booking, array $fields, array $merchant, ?string $source): void
    {
        $signature = $fields['signature'];
        unset($fields['signature']);
        $parameters = http_build_query($fields);
        abort_unless(hash_equals($this->signature($parameters, $merchant['passphrase']), $signature)
            && $this->isPayfastSource($source), 401, 'Payfast notification could not be authenticated.');
        abort_unless(($fields['merchant_id'] ?? null) === $booking->payfast_merchant_id
            && $merchant['merchant_id'] === $booking->payfast_merchant_id, 409, 'The payment merchant does not match this booking.');
        if (($fields['payment_status'] ?? null) !== 'COMPLETE') {
            return;
        }
        $id = $fields['pf_payment_id'] ?? '';
        $gross = $fields['amount_gross'] ?? '';
        abort_unless(preg_match('/^\d{1,30}$/D', $id) && preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $gross)
            && (int) round((float) $gross * 100) === (int) round((float) $booking->price * 100), 409,
            'The payment could not be matched to this booking.');
        if ($booking->online_paid_at) {
            abort_unless($booking->payfast_transaction_id === $id, 409, 'This booking already has a different verified payment.');

            return;
        }
        try {
            $result = Http::withOptions(['allow_redirects' => false])->timeout(8)
                ->withBody($parameters, 'application/x-www-form-urlencoded')
                ->post(self::SANDBOX.'/eng/query/validate');
        } catch (ConnectionException) {
            abort(503, 'Payfast verification is temporarily unavailable.');
        }
        abort_unless($result->successful() && trim($result->body()) === 'VALID', 502, 'Payfast did not verify the payment.');

        DB::transaction(function () use ($booking, $id) {
            $shop = Shop::whereKey($booking->shop_id)->lockForUpdate()->firstOrFail();
            $current = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if ($current->online_paid_at) {
                abort_unless($current->payfast_transaction_id === $id, 409);

                return;
            }
            abort_if(Booking::where('payfast_transaction_id', $id)->exists(), 409, 'This Payfast transaction has already been recorded.');
            $confirmed = $shop->status === 'active' && $current->status === 'pending_payment'
                && $current->payment_hold_until?->isFuture()
                && CarbonImmutable::parse($current->starts_at)->isFuture();
            $current->update([
                'status' => $confirmed ? 'confirmed' : 'payment_review',
                'online_paid_at' => now(),
                'payfast_transaction_id' => $id,
            ]);
        }, 5);
    }

    public function isPayfastSource(?string $ip): bool
    {
        foreach (['sandbox.payfast.co.za', 'www.payfast.co.za', 'w1w.payfast.co.za', 'w2w.payfast.co.za'] as $host) {
            if (in_array($ip, gethostbynamel($host) ?: [], true)) {
                return true;
            }
        }

        return false;
    }

    private function signature(string $parameters, string $passphrase): string
    {
        return md5($parameters.'&passphrase='.urlencode(trim($passphrase)));
    }
}
