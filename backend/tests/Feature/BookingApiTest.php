<?php

namespace Tests\Feature;

use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BookingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_create_a_booking(): void
    {
        $payload = $this->validBookingPayload();

        $response = $this->postJson('/api/bookings', $payload);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Your appointment has been booked successfully.')
            ->assertJsonPath('booking.service_name', 'Skin Fade')
            ->assertJsonPath('booking.status', 'confirmed');

        $this->assertDatabaseHas('bookings', [
            'barber_id' => 1,
            'email' => 'customer@example.com',
            'status' => 'confirmed',
        ]);

        $booking = Booking::firstOrFail();

        $this->assertSame($payload['booking_date'], $booking->booking_date->toDateString());
        $this->assertSame('10:00', $booking->booking_time);
    }

    public function test_booking_requires_valid_customer_and_appointment_details(): void
    {
        $response = $this->postJson('/api/bookings', [
            'booking_date' => Carbon::yesterday()->toDateString(),
            'email' => 'not-an-email',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
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
            ]);
    }

    public function test_customer_cannot_book_an_occupied_barber_time(): void
    {
        $payload = $this->validBookingPayload();

        $this->postJson('/api/bookings', $payload)->assertCreated();

        $this->postJson('/api/bookings', [
            ...$payload,
            'customer_name' => 'Second Customer',
            'email' => 'second@example.com',
        ])
            ->assertConflict()
            ->assertJsonPath(
                'message',
                'That time is already booked. Please choose another time.'
            );

        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_availability_returns_only_active_bookings_for_the_barber_and_date(): void
    {
        $date = Carbon::tomorrow()->toDateString();

        Booking::create([
            ...$this->validBookingPayload(),
            'booking_date' => $date,
            'booking_time' => '10:00',
            'status' => 'confirmed',
        ]);

        Booking::create([
            ...$this->validBookingPayload(),
            'barber_id' => 2,
            'barber_name' => 'Daniel Brooks',
            'booking_date' => $date,
            'booking_time' => '11:00',
            'status' => 'confirmed',
        ]);

        Booking::create([
            ...$this->validBookingPayload(),
            'booking_date' => $date,
            'booking_time' => '12:00',
            'status' => 'cancelled',
        ]);

        $this->getJson("/api/bookings/availability?barber_id=1&booking_date={$date}")
            ->assertOk()
            ->assertExactJson([
                'booked_times' => ['10:00'],
            ]);
    }

    private function validBookingPayload(): array
    {
        return [
            'service_id' => 2,
            'service_name' => 'Skin Fade',
            'barber_id' => 1,
            'barber_name' => 'Liam Carter',
            'booking_date' => Carbon::tomorrow()->toDateString(),
            'booking_time' => '10:00',
            'duration' => 50,
            'customer_name' => 'Test Customer',
            'email' => 'customer@example.com',
            'phone' => '+27 82 555 0100',
            'notes' => 'Please keep the top longer.',
        ];
    }
}
