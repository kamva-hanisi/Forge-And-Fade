<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingPayment;
use App\Services\ShopAccess;
use App\Services\ShopCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BookingPaymentController extends Controller
{
    public function store(Request $request, int $booking)
    {
        ShopAccess::authorize($request, 'payments.record');

        return DB::transaction(function () use ($request, $booking) {
            $shop = ShopCatalog::lock($request);
            $current = Booking::where('shop_id', $shop->id)->whereKey($booking)->lockForUpdate()->firstOrFail();
            $input = $request->validate(['amount' => 'required|numeric|min:0.01|max:99999999.99|decimal:0,2',
                'method' => ['required', Rule::in(['cash', 'card', 'bank_transfer', 'other'])],
                'received_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:'.now($shop->timezone)->toDateString()]]);
            abort_if($current->status === 'cancelled', 422, 'A cancelled appointment cannot receive a new payment record.');
            if ($current->activePayment()->exists()) {
                return response()->json(['message' => 'This appointment already has a payment record. Refresh the list.'], 409);
            }
            $payment = BookingPayment::create([...$input, 'booking_id' => $current->id, 'shop_id' => $shop->id,
                'currency' => $current->currency, 'recorded_by' => $request->user()->id]);
            ShopAccess::audit($request, 'payment.recorded', $payment->id);

            return response()->json(['message' => 'Payment receipt recorded. No money was charged by this website.', 'payment' => $payment], 201)->header('Cache-Control', 'no-store');
        }, 5);
    }

    public function void(Request $request, int $payment)
    {
        ShopAccess::authorize($request, 'shop.manage');

        return DB::transaction(function () use ($request, $payment) {
            $shop = ShopCatalog::lock($request);
            $receipt = BookingPayment::where('shop_id', $shop->id)->findOrFail($payment);
            Booking::where('shop_id', $shop->id)->whereKey($receipt->booking_id)->lockForUpdate()->firstOrFail();
            $receipt = BookingPayment::whereKey($receipt->id)->lockForUpdate()->firstOrFail();
            $input = $request->validate(['reason' => 'required|string|max:500']);
            if ($receipt->voided_at) {
                return response()->json(['message' => 'This payment record was already voided.'], 409);
            }
            $receipt->update(['voided_at' => now(), 'voided_by' => $request->user()->id, 'void_reason' => $input['reason']]);
            ShopAccess::audit($request, 'payment.voided', $receipt->id);

            return response()->json(['message' => 'Receipt voided for reporting. This did not refund any money.'])->header('Cache-Control', 'no-store');
        }, 5);
    }
}
