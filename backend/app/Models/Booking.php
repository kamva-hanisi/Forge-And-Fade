<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Booking extends Model
{
    protected $fillable = [
        'service_id',
        'service_name',
        'barber_id',
        'barber_name',
        'booking_date',
        'booking_time',
        'duration',
        'customer_name',
        'email',
        'phone',
        'notes',
        'status',
        'price',
        'currency',
        'timezone',
        'shop_id',
    ];

    protected $casts = [
        'booking_date' => 'date:Y-m-d',
        'price' => 'decimal:2',
        'shop_id' => 'integer',
    ];

    protected $appends = ['starts_at', 'ends_at'];

    protected static function booted(): void
    {
        static::creating(function (Booking $booking) {
            $booking->reference = 'FF-'.Str::upper(Str::random(16));
            $booking->shop_id ??= request()->attributes->get('shop')?->id ?? 1;
            $booking->timezone = Shop::findOrFail($booking->shop_id)->timezone;
        });
    }

    private function appointmentStart(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->booking_date->toDateString().' '.$this->booking_time, $this->timezone);
    }

    protected function startsAt(): Attribute
    {
        return Attribute::get(fn () => $this->appointmentStart()->toIso8601String());
    }

    protected function endsAt(): Attribute
    {
        return Attribute::get(fn () => $this->appointmentStart()->addMinutes($this->duration)->toIso8601String());
    }
}
