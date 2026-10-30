<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CustomerBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-02 09:00:00', 'Africa/Johannesburg'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function payload(): array
    {
        return ['service_id' => 2, 'barber_id' => 1, 'booking_date' => '2026-11-03', 'booking_time' => '10:00',
            'customer_name' => 'Lookup QA', 'email' => 'lookup@example.com', 'phone' => '0712345678', 'notes' => 'Private note'];
    }

    private function credentials(): array
    {
        $booking = $this->postJson('/api/bookings', $this->payload())->assertCreated()->json('booking');

        return ['reference' => $booking['reference'], 'email' => 'lookup@example.com'];
    }

    public function test_lookup_returns_only_appointment_details_without_customer_or_staff_data(): void
    {
        $credentials = $this->credentials();
        $response = $this->postJson('/api/bookings/lookup', [...$credentials, 'email' => 'LOOKUP@example.com'])
            ->assertOk()->assertJsonPath('can_cancel', true)->assertJsonPath('booking.service_name', 'Skin Fade')->assertJsonPath('booking.booking_date', '2026-11-03');
        foreach (['email', 'phone', 'notes', 'customer_name', 'id', 'shop_id', 'active_payment'] as $field) {
            $response->assertJsonMissingPath('booking.'.$field);
        }
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_wrong_credentials_and_other_shop_cannot_lookup_or_cancel(): void
    {
        $credentials = $this->credentials();
        Shop::create(['name' => 'Other', 'slug' => 'other', 'timezone' => 'Africa/Johannesburg']);
        foreach (['lookup', 'cancel'] as $action) {
            $this->postJson('/api/bookings/'.$action, [...$credentials, 'email' => 'wrong@example.com', 'revision' => 0])->assertNotFound();
            $this->postJson('/api/bookings/'.$action, [...$credentials, 'shop' => 'other', 'revision' => 0])->assertNotFound();
        }
        $this->assertSame('confirmed', Booking::firstOrFail()->status);
        $this->postJson('/api/bookings/lookup', ['reference' => [], 'email' => 'invalid'])->assertUnprocessable();
    }

    public function test_cancel_is_idempotent_and_releases_slot_without_changing_receipt(): void
    {
        $credentials = $this->credentials();
        $this->postJson('/api/bookings/cancel', [...$credentials, 'revision' => 0])->assertOk()
            ->assertJsonPath('booking.status', 'cancelled')->assertJsonPath('booking.revision', 1)->assertJsonPath('can_cancel', false);
        $this->postJson('/api/bookings/cancel', [...$credentials, 'revision' => 0])->assertOk()->assertJsonPath('booking.revision', 1);
        $this->assertDatabaseHas('bookings', ['reference' => $credentials['reference'], 'price' => '250.00', 'duration' => 50]);
        $this->postJson('/api/bookings', $this->payload())->assertCreated();
    }

    public function test_started_and_changed_appointments_cannot_be_cancelled(): void
    {
        $credentials = $this->credentials();
        Booking::query()->update(['revision' => 1]);
        $this->postJson('/api/bookings/cancel', [...$credentials, 'revision' => 0])->assertConflict();
        Carbon::setTestNow(Carbon::parse('2026-11-03 10:00:00', 'Africa/Johannesburg'));
        $this->postJson('/api/bookings/lookup', $credentials)->assertOk()->assertJsonPath('can_cancel', false);
        $this->postJson('/api/bookings/cancel', [...$credentials, 'revision' => 1])->assertConflict();
        $this->assertSame('confirmed', Booking::firstOrFail()->status);
    }

    public function test_lookup_attempts_are_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/bookings/lookup', ['reference' => 'FF-AAAAAAAAAAAAAAAA', 'email' => 'missing@example.com'])->assertNotFound();
        }
        $this->postJson('/api/bookings/lookup', ['reference' => 'FF-AAAAAAAAAAAAAAAA', 'email' => 'missing@example.com'])->assertStatus(429);
    }
}
