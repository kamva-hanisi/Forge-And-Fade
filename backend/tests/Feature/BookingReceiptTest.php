<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BookingReceiptTest extends TestCase
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

    private function updateShopSettings(array $changes): void
    {
        $shop = Shop::findOrFail(1);
        $shop->update(['settings' => array_replace_recursive($shop->settings ?? [], $changes)]);
    }

    private function payload(array $changes = []): array
    {
        return [...['service_id' => 2, 'barber_id' => 1, 'booking_date' => '2026-11-03', 'booking_time' => '10:00',
            'customer_name' => 'Receipt QA', 'email' => 'receipt@example.com', 'phone' => '0712345678'], ...$changes];
    }

    public function test_receipt_values_are_generated_and_priced_by_the_server(): void
    {
        $response = $this->postJson('/api/bookings', $this->payload(['price' => 1, 'currency' => 'USD',
            'timezone' => 'America/New_York', 'reference' => 'FAKE', 'quoted_price' => 250]))->assertCreated()
            ->assertJsonPath('booking.price', '250.00')->assertJsonPath('booking.currency', 'ZAR')
            ->assertJsonPath('booking.timezone', 'Africa/Johannesburg')
            ->assertJsonPath('booking.starts_at', '2026-11-03T10:00:00+02:00')
            ->assertJsonPath('booking.ends_at', '2026-11-03T10:50:00+02:00');
        $this->assertMatchesRegularExpression('/^FF-[A-Z0-9]{16}$/', $response->json('booking.reference'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertDatabaseHas('bookings', ['reference' => $response->json('booking.reference'), 'price' => '250.00']);
    }

    public function test_references_are_distinct_for_two_bookings(): void
    {
        $first = $this->postJson('/api/bookings', $this->payload())->assertCreated();
        $second = $this->postJson('/api/bookings', $this->payload(['booking_time' => '11:00']))->assertCreated();
        $this->assertNotSame($first->json('booking.reference'), $second->json('booking.reference'));
    }

    public function test_outdated_quote_is_rejected_before_creating_a_booking(): void
    {
        $this->updateShopSettings(['services' => [2 => ['price' => 260]]]);
        $this->postJson('/api/bookings', $this->payload(['quoted_price' => 250]))->assertUnprocessable()->assertJsonValidationErrors('quoted_price');
        $this->assertDatabaseCount('bookings', 0);
        $this->postJson('/api/bookings', $this->payload(['quoted_price' => 260]))->assertCreated()->assertJsonPath('booking.price', '260.00');
    }

    public function test_reschedule_and_cancel_preserve_reference_price_and_timezone(): void
    {
        $created = $this->postJson('/api/bookings', $this->payload())->assertCreated();
        $booking = Booking::firstOrFail();
        $this->updateShopSettings(['services' => [2 => ['price' => 999, 'duration' => 90]]]);
        $admin = User::factory()->create();
        $this->grantShopRole($admin);
        $this->withToken($admin->createToken('test', ['admin'])->plainTextToken)
            ->patchJson('/api/admin/bookings/'.$booking->id, ['action' => 'reschedule', 'barber_id' => 2,
                'booking_date' => '2026-11-04', 'booking_time' => '11:00', 'revision' => 0])->assertOk()
            ->assertJsonPath('booking.reference', $created->json('booking.reference'))
            ->assertJsonPath('booking.price', '250.00')->assertJsonPath('booking.duration', 50)
            ->assertJsonPath('booking.starts_at', '2026-11-04T11:00:00+02:00')
            ->assertJsonPath('booking.ends_at', '2026-11-04T11:50:00+02:00');
        $this->patchJson('/api/admin/bookings/'.$booking->id, ['action' => 'cancel', 'revision' => 1])
            ->assertOk()->assertJsonPath('booking.reference', $created->json('booking.reference'))
            ->assertJsonPath('booking.price', '250.00')->assertJsonPath('booking.timezone', 'Africa/Johannesburg');
    }

    public function test_configured_days_off_are_unavailable_in_both_endpoints(): void
    {
        $this->updateShopSettings(['closed_dates' => ['2026-11-03']]);
        $this->getJson('/api/bookings/availability?service_id=2&barber_id=1&booking_date=2026-11-03')
            ->assertOk()->assertJsonPath('available_times', []);
        $this->postJson('/api/bookings', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('booking_time');
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_expired_times_are_checked_as_instants_in_the_shop_timezone(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-03T08:05:00+00:00'));
        $times = $this->getJson('/api/bookings/availability?service_id=2&barber_id=1&booking_date=2026-11-03')->assertOk()->json('available_times');
        $this->assertNotContains('10:00', $times);
        $this->assertContains('10:10', $times);
        $this->postJson('/api/bookings', $this->payload())->assertUnprocessable();
        $this->postJson('/api/bookings', $this->payload(['booking_time' => '10:10']))->assertCreated();
    }
}
