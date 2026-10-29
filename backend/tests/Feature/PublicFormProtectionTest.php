<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicFormProtectionTest extends TestCase
{
    use RefreshDatabase;

    private function contact(): array
    {
        return ['name' => 'Guest', 'email' => 'guest@example.com', 'message' => 'Are walk-ins welcome?'];
    }

    public function test_contact_honeypot_rejects_bots_without_storing_a_message(): void
    {
        $this->postJson('/api/contact', [...$this->contact(), 'website' => 'https://spam.example'])
            ->assertUnprocessable()->assertJsonValidationErrors('website');
        $this->postJson('/api/contact', [...$this->contact(), 'website' => ['unexpected']])
            ->assertUnprocessable();
        $this->assertDatabaseCount('contact_messages', 0);
        $this->postJson('/api/contact', [...$this->contact(), 'website' => ''])->assertCreated();
        $this->assertDatabaseCount('contact_messages', 1);
    }

    public function test_booking_honeypot_rejects_before_any_booking_is_saved(): void
    {
        $payload = ['customer_name' => 'Guest', 'email' => 'guest@example.com', 'phone' => '0712345678',
            'service_id' => 2, 'barber_id' => 1, 'booking_date' => now()->next('Monday')->toDateString(),
            'booking_time' => '10:00', 'website' => 'spam'];
        $this->postJson('/api/bookings', $payload)->assertUnprocessable()->assertJsonValidationErrors('website');
        $this->assertDatabaseCount('bookings', 0);
        $payload['website'] = '';
        $this->postJson('/api/bookings', $payload)->assertCreated();
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_contact_limit_returns_retry_after_and_does_not_store_blocked_requests(): void
    {
        config(['public_forms.contact_per_minute' => 2]);
        $this->postJson('/api/contact', $this->contact())->assertCreated();
        $this->postJson('/api/contact', $this->contact())->assertCreated();
        $response = $this->postJson('/api/contact', $this->contact())->assertStatus(429)->assertHeader('Retry-After');
        $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
        $this->assertDatabaseCount('contact_messages', 2);
        $this->travel(61)->seconds();
        $this->postJson('/api/contact', $this->contact())->assertCreated();
    }

    public function test_invalid_booking_attempts_are_limited_and_other_ips_are_independent(): void
    {
        config(['public_forms.booking_per_minute' => 2]);
        $this->postJson('/api/bookings', [])->assertUnprocessable();
        $this->postJson('/api/bookings', [])->assertUnprocessable();
        $this->postJson('/api/bookings', [])->assertStatus(429)->assertHeader('Retry-After');
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])->postJson('/api/bookings', [])->assertUnprocessable();
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_hourly_limit_still_applies_after_minute_limits_reset(): void
    {
        config(['public_forms.booking_per_minute' => 1, 'public_forms.booking_per_hour' => 2]);
        $this->postJson('/api/bookings', [])->assertUnprocessable();
        $this->travel(61)->seconds();
        $this->postJson('/api/bookings', [])->assertUnprocessable();
        $this->travel(61)->seconds();
        $this->postJson('/api/bookings', [])->assertStatus(429);
    }

    public function test_catalog_and_availability_share_a_limit_separate_from_forms(): void
    {
        config(['public_forms.availability_per_minute' => 2]);
        $this->getJson('/api/bookings/catalog')->assertOk();
        $this->getJson('/api/bookings/availability')->assertUnprocessable();
        $this->getJson('/api/bookings/catalog')->assertStatus(429)->assertHeader('Retry-After');
        $this->postJson('/api/contact', $this->contact())->assertCreated();
    }

    public function test_oversized_submissions_are_rejected_before_persistence(): void
    {
        foreach (['contact', 'bookings'] as $path) {
            $this->postJson('/api/'.$path, ['message' => str_repeat('x', 17000)])->assertStatus(413);
        }
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_retry_header_is_exposed_to_the_allowed_frontend_origin(): void
    {
        $this->withHeader('Origin', 'http://localhost:5173')->getJson('/api/bookings/catalog')->assertOk()
            ->assertHeader('Access-Control-Expose-Headers', 'Retry-After');
    }
}
