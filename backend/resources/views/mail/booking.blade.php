<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><title>{{ $title }}</title></head>
<body style="margin:0;background:#f6f5f2;color:#171717;font-family:Arial,sans-serif;line-height:1.6">
<main style="max-width:600px;margin:32px auto;background:#fff;padding:32px">
    <p style="color:#9a763f;font-weight:bold">{{ $shop->name }}</p>
    <h1 style="font-family:Georgia,serif">{{ $title }}</h1>
    <dl>
        <dt>Reference</dt><dd>{{ $booking->reference }}</dd>
        <dt>Service</dt><dd>{{ $booking->service_name }} ({{ $booking->duration }} minutes)</dd>
        <dt>Barber</dt><dd>{{ $booking->barber_name }}</dd>
        <dt>Date</dt><dd>{{ $booking->booking_date->toDateString() }}</dd>
        <dt>Time</dt><dd>{{ substr($booking->booking_time, 0, 5) }} ({{ $booking->timezone }})</dd>
        <dt>Service price</dt><dd>{{ $booking->currency }} {{ $booking->price }}</dd>
        <dt>Status</dt><dd>{{ $booking->status }}</dd>
        <dt>Location</dt><dd>{{ $shop->address ?: 'Contact the shop for directions.' }}</dd>
    </dl>
    <p><a href="{{ $manageUrl }}" style="color:#765a30">Manage your appointment</a></p>
    <p>Use your reference and the email you booked with. Keep your reference private.</p>
    <p>Online cancellation is available before the appointment starts. Contact the shop to reschedule.</p>
    <p>This message is not a payment receipt and cancellation does not issue a refund.</p>
    @if($shop->phone)
        <p>Shop phone: {{ $shop->phone }}</p>
    @endif
</main>
</body>
</html>
