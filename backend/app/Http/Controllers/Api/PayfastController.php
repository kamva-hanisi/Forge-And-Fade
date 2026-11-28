<?php

namespace App\Http\Controllers\Api;

use App\Models\Booking;
use App\Models\Shop;
use App\Services\PayfastCheckout;
use Illuminate\Http\Request;

class PayfastController
{
    public function redirectToBooking(string $reference)
    {
        return redirect()->away(rtrim(config('admin.frontend_url'), '/').'/#/booking/payment?reference='.$reference)
            ->header('Cache-Control', 'no-store');
    }

    public function status(string $reference)
    {
        $booking = Booking::where('payment_reference', $reference)->firstOrFail();
        $shop = Shop::findOrFail($booking->shop_id);

        return response()->json([
            'status' => $booking->status === 'pending_payment' && ! $booking->payment_hold_until?->isFuture()
                ? 'expired' : $booking->status,
            'booking_reference' => $booking->status === 'confirmed' ? $booking->reference : null,
            'shop' => $shop->only(['name', 'slug']),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function notify(Request $request, PayfastCheckout $checkout)
    {
        $fields = $checkout->notification($request->getContent());
        $reference = $fields['m_payment_id'] ?? '';
        abort_unless(preg_match('/^PF-[A-Za-z0-9]{32}$/D', $reference), 400);
        $booking = Booking::where('payment_reference', $reference)->firstOrFail();
        $merchant = $checkout->merchant(Shop::findOrFail($booking->shop_id));
        abort_unless($merchant, 404);
        // Render's public ingress overwrites CF-Connecting-IP with the actual sender.
        $source = config('payfast.render_ingress') ? $request->header('CF-Connecting-IP') : $request->ip();
        $checkout->verify($booking, $fields, $merchant, $source);

        return response('', 200);
    }
}
