<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BookingApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-02 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

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
        $this->assertSame('10:00', substr($booking->booking_time, 0, 5));
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
                'barber_id',
                'booking_date',
                'booking_time',
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

        $response = $this->getJson("/api/bookings/availability?service_id=2&barber_id=1&booking_date={$date}")
            ->assertOk()
            ->assertJsonPath('timezone', 'Africa/Johannesburg');
        $times = $response->json('available_times');
        $this->assertNotContains('10:00', $times);
        $this->assertNotContains('09:20', $times);
        $this->assertContains('10:50', $times);
        $this->assertContains('11:00', $times);
        $this->assertContains('12:00', $times);
    }

    public function test_overlap_in_both_directions_and_containment_is_rejected(): void
    {
        $payload = $this->validBookingPayload();
        $this->postJson('/api/bookings', $payload)->assertCreated();
        foreach (['09:20', '10:00', '10:20', '10:40'] as $time) {
            $this->postJson('/api/bookings', [...$payload, 'booking_time' => $time])->assertConflict();
        }
        $this->postJson('/api/bookings', [...$payload, 'service_id' => 6, 'booking_time' => '10:10'])->assertConflict();
        $this->postJson('/api/bookings', [...$payload, 'service_id' => 4, 'booking_time' => '09:50'])->assertConflict();
        $this->postJson('/api/bookings', [...$payload, 'booking_time' => '09:10'])->assertCreated();
        $this->postJson('/api/bookings', [...$payload, 'booking_time' => '10:50'])->assertCreated();
        $this->postJson('/api/bookings', [...$payload, 'barber_id' => 2])->assertCreated();
        $this->postJson('/api/bookings', [...$payload, 'booking_date' => '2026-11-04'])->assertCreated();
    }

    public function test_cancelled_slot_can_be_reused_repeatedly(): void
    {
        $payload = $this->validBookingPayload();
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $response = $this->postJson('/api/bookings', $payload)->assertCreated();
            Booking::findOrFail($response->json('booking.id'))->update(['status' => 'cancelled']);
        }
        $this->postJson('/api/bookings', $payload)->assertCreated();
        $this->assertDatabaseCount('bookings', 4);
    }

    public function test_catalog_values_are_trusted_over_customer_input(): void
    {
        $this->postJson('/api/bookings', [
            ...$this->validBookingPayload(), 'service_name' => 'Fake', 'barber_name' => 'Fake', 'duration' => 1, 'status' => 'cancelled',
        ])->assertCreated()->assertJsonPath('booking.duration', 50)
            ->assertJsonPath('booking.service_name', 'Skin Fade')->assertJsonPath('booking.barber_name', 'Liam Carter')
            ->assertJsonPath('booking.status', 'confirmed');
        foreach (['service_id', 'barber_id'] as $field) {
            $this->postJson('/api/bookings', [...$this->validBookingPayload(), $field => 999])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }

    public function test_hours_breaks_grid_and_past_times_are_enforced(): void
    {
        $payload = $this->validBookingPayload();
        foreach (['07:50', '12:20', '13:00', '13:50', '17:20', '18:00', '10:01'] as $time) {
            $this->postJson('/api/bookings', [...$payload, 'booking_time' => $time])->assertUnprocessable()->assertJsonValidationErrors('booking_time');
        }
        $this->postJson('/api/bookings', [...$payload, 'booking_date' => '2026-11-08'])->assertUnprocessable();
        $this->postJson('/api/bookings', [...$payload, 'booking_date' => '2026-11-02', 'booking_time' => '08:00'])->assertUnprocessable();
        $this->assertDatabaseCount('booking_day_locks', 0);
        foreach (['08:00', '12:10', '14:00', '17:10'] as $time) {
            $this->postJson('/api/bookings', [...$payload, 'booking_time' => $time])->assertCreated();
        }
    }

    public function test_availability_uses_full_duration_and_closed_days(): void
    {
        $short = $this->getJson('/api/bookings/availability?service_id=6&barber_id=1&booking_date=2026-11-03')->assertOk()->json('available_times');
        $long = $this->getJson('/api/bookings/availability?service_id=4&barber_id=1&booking_date=2026-11-03')->assertOk()->json('available_times');
        $this->assertContains('17:40', $short);
        $this->assertNotContains('17:40', $long);
        $this->assertNotContains('12:00', $long);
        $this->getJson('/api/bookings/availability?service_id=2&barber_id=1&booking_date=2026-11-08')->assertOk()->assertJsonPath('available_times', []);
        $this->getJson('/api/bookings/availability?service_id=999&barber_id=1&booking_date=2026-11-03')->assertUnprocessable();
        $this->getJson('/api/bookings/catalog')->assertOk()->assertJsonCount(6, 'services')->assertJsonCount(3, 'barbers');
    }

    public function test_any_barber_uses_remaining_capacity_and_cancelled_slots(): void
    {
        $payload = [...$this->validBookingPayload(), 'barber_id' => 'any'];
        foreach ([1, 2, 3] as $barber) {
            $this->postJson('/api/bookings', $payload)->assertCreated()->assertJsonPath('booking.barber_id', $barber);
        }
        $this->postJson('/api/bookings', $payload)->assertConflict();
        $this->assertNotContains('10:00', $this->getJson('/api/bookings/availability?service_id=2&barber_id=any&booking_date=2026-11-03')->assertOk()->json('available_times'));
        Booking::where('barber_id', 2)->update(['status' => 'cancelled']);
        $this->assertContains('10:00', $this->getJson('/api/bookings/availability?service_id=2&barber_id=any&booking_date=2026-11-03')->assertOk()->json('available_times'));
        $this->postJson('/api/bookings', $payload)->assertCreated()->assertJsonPath('booking.barber_id', 2);
    }

    public function test_any_barber_respects_staff_days_off_and_inactive_barbers(): void
    {
        Shop::findOrFail(1)->update(['settings' => [
            'barbers' => [1 => ['name' => 'Unavailable', 'active' => false], 2 => ['name' => 'Off duty', 'active' => true], 3 => ['name' => 'Available', 'active' => true]],
            'staff_schedules' => [2 => ['hours' => [], 'breaks' => [], 'closed_dates' => []]],
        ]]);
        $this->postJson('/api/bookings', [...$this->validBookingPayload(), 'barber_id' => 'any'])
            ->assertCreated()->assertJsonPath('booking.barber_id', 3)->assertJsonPath('booking.barber_name', 'Available');
    }

    public function test_any_barber_rejects_closed_times_and_empty_catalog(): void
    {
        $payload = [...$this->validBookingPayload(), 'barber_id' => 'any', 'booking_time' => '03:00'];
        $this->postJson('/api/bookings', $payload)->assertConflict();
        $this->postJson('/api/bookings', [...$payload, 'barber_id' => 'anyone'])->assertUnprocessable();
        Shop::findOrFail(1)->update(['settings' => ['barbers' => []]]);
        $this->postJson('/api/bookings', $payload)->assertUnprocessable()->assertJsonValidationErrors('barber_id');
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
