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
    public function index(Request $request)
    {
        $shop = ShopAccess::authorize($request, 'payments.read');
        $input = $request->validate([
            'type' => ['nullable', Rule::in(['cash', 'manual', 'payfast'])],
            'page' => 'nullable|integer|min:1',
        ]);
        $receipts = DB::table('booking_payments as receipt')
            ->join('bookings as booking', function ($join) {
                $join->on('booking.id', '=', 'receipt.booking_id')->on('booking.shop_id', '=', 'receipt.shop_id');
            })
            ->where('receipt.shop_id', $shop->id)
            ->selectRaw("'manual' as type, receipt.id as entry_id, booking.reference as booking_reference,
                booking.customer_name, booking.service_name, receipt.amount, receipt.currency, receipt.method,
                receipt.received_on as paid_on, CASE WHEN receipt.voided_at IS NULL THEN 'received' ELSE 'voided' END as payment_status,
                NULL as transaction_id");
        $online = DB::table('bookings')->where('shop_id', $shop->id)
            ->where('payment_choice', 'online')->whereNotNull('online_paid_at')
            ->selectRaw("CASE WHEN payfast_transaction_id IS NULL THEN 'legacy' ELSE 'payfast' END as type,
                id as entry_id, reference as booking_reference, customer_name, service_name, price as amount, currency,
                'online' as method, DATE(online_paid_at) as paid_on,
                CASE WHEN status = 'payment_review' THEN 'review' ELSE 'verified' END as payment_status,
                payfast_transaction_id as transaction_id");
        $page = DB::query()->fromSub($receipts->unionAll($online), 'payments')
            ->when($input['type'] ?? null, fn ($query, $type) => $type === 'cash'
                ? $query->where('type', 'manual')->where('method', 'cash')
                : $query->where('type', $type))
            ->orderByDesc('paid_on')->orderBy('type')->orderByDesc('entry_id')->paginate(20);

        return response()->json($page)->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, int $booking)
    {
        ShopAccess::authorize($request, 'payments.record');
        abort_unless(ShopAccess::allows($request, 'payments.read'), 403);

        return DB::transaction(function () use ($request, $booking) {
            $shop = ShopCatalog::lock($request);
            $current = Booking::where('shop_id', $shop->id)->whereKey($booking)->lockForUpdate()->firstOrFail();
            $input = $request->validate(['amount' => 'required|numeric|min:0.01|max:99999999.99|decimal:0,2',
                'method' => ['required', Rule::in(['cash', 'card', 'bank_transfer', 'other'])],
                'received_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before_or_equal:'.now($shop->timezone)->toDateString()]]);
            abort_if($current->status === 'cancelled', 422, 'A cancelled appointment cannot receive a new payment record.');
            abort_if($current->payment_choice === 'online', 422, 'Online checkout tracks this appointment’s payment.');
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
        ShopAccess::authorize($request, 'payments.void');

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
