<?php

namespace App\Models;

use App\Services\BookingEmails;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'payment_choice',
        'payment_reference',
        'payfast_merchant_id',
        'payfast_transaction_id',
        'payment_hold_until',
        'online_paid_at',
        'timezone',
        'shop_id',
    ];

    protected $casts = [
        'booking_date' => 'date:Y-m-d',
        'price' => 'decimal:2',
        'shop_id' => 'integer',
        'payment_hold_until' => 'immutable_datetime',
        'online_paid_at' => 'immutable_datetime',
    ];

    protected $hidden = ['payment_reference', 'payfast_merchant_id', 'payfast_transaction_id'];

    protected $appends = ['starts_at', 'ends_at'];

    public function activePayment(): HasOne
    {
        return $this->hasOne(BookingPayment::class)->whereNull('voided_at');
    }

    public function scopeByStart(Builder $query, CarbonInterface $clock, bool $past): Builder
    {
        $date = $clock->toDateString();
        $time = $clock->format('H:i:s');

        return $query->where('status', 'confirmed')->where(function (Builder $starts) use ($date, $time, $past) {
            $starts->where('booking_date', $past ? '<' : '>', $date)
                ->orWhere(fn (Builder $sameDay) => $sameDay->whereDate('booking_date', $date)
                    ->whereTime('booking_time', $past ? '<' : '>=', $time));
        });
    }

    public function scopeHoldingSlot(Builder $query): Builder
    {
        return $query->where(fn (Builder $slot) => $slot->where('status', 'confirmed')
            ->orWhere(fn (Builder $pending) => $pending->where('status', 'pending_payment')->where('payment_hold_until', '>', now())));
    }

    protected static function booted(): void
    {
        static::created(function (Booking $booking) {
            if ($booking->status === 'confirmed') {
                BookingEmails::record($booking, 'confirmation');
            }
        });
        static::updated(function (Booking $booking) {
            if ($booking->getOriginal('status') === 'pending_payment') {
                if ($booking->status === 'confirmed') {
                    BookingEmails::record($booking, 'confirmation');
                }

                return;
            }
            if ($booking->wasChanged(['status', 'booking_date', 'booking_time', 'barber_id'])) {
                BookingEmails::record($booking, $booking->status === 'cancelled' ? 'cancelled' : 'changed');
            }
        });
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
