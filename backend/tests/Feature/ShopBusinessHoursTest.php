<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ShopBusinessHoursTest extends TestCase
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

    private function authenticate(string $role = 'owner'): void
    {
        $user = User::factory()->create();
        $this->grantShopRole($user, $role, 1, $role === 'barber' ? 1 : null);
        app('auth')->forgetGuards();
        $this->withToken($user->createToken('business-hours-test', ['admin'])->plainTextToken);
    }

    private function schedule(array $changes = []): array
    {
        return $changes + ['version' => 0, 'hours' => array_fill(1, 7, ['09:00', '16:00']),
            'breaks' => [['11:00', '12:00']], 'closed_dates' => ['2026-11-04']];
    }

    private function booking(array $changes = []): array
    {
        return $changes + ['service_id' => 2, 'barber_id' => 1, 'booking_date' => '2026-11-03', 'booking_time' => '10:00',
            'customer_name' => 'Hours QA', 'email' => 'hours@example.com', 'phone' => '0712345678'];
    }

    public function test_only_owner_can_change_hours_and_never_another_shops_settings(): void
    {
        $this->putJson('/api/admin/management/business-hours', $this->schedule())->assertUnauthorized();
        foreach (['manager', 'barber'] as $role) {
            $this->authenticate($role);
            $this->putJson('/api/admin/management/business-hours', $this->schedule())->assertForbidden();
        }
        $other = Shop::create(['name' => 'Other', 'slug' => 'hours-other', 'settings' => ['hours' => []]]);
        $this->authenticate();
        $this->withHeader('X-Shop-Id', $other->id)->putJson('/api/admin/management/business-hours', $this->schedule())->assertForbidden();
        $this->assertSame(['hours' => []], $other->fresh()->settings);
    }

    public function test_saved_hours_breaks_and_closures_apply_to_public_and_staff_booking(): void
    {
        $this->authenticate();
        $catalog = Shop::findOrFail(1)->settings['services'];
        $this->putJson('/api/admin/management/business-hours', $this->schedule())->assertOk()
            ->assertJsonPath('business_schedule.hours.2', ['09:00', '16:00'])->assertJsonPath('timezone', 'Africa/Johannesburg');
        $times = $this->getJson('/api/bookings/availability?service_id=2&barber_id=any&booking_date=2026-11-03')->assertOk()->json('available_times');
        $this->assertContains('09:00', $times);
        foreach (['08:00', '10:20', '11:00', '15:20'] as $time) {
            $this->assertNotContains($time, $times);
            $this->postJson('/api/bookings', $this->booking(['booking_time' => $time]))->assertUnprocessable();
            $this->postJson('/api/admin/bookings', $this->booking(['booking_time' => $time]))->assertUnprocessable();
        }
        $this->getJson('/api/bookings/availability?service_id=2&barber_id=1&booking_date=2026-11-04')->assertOk()->assertJsonPath('available_times', []);
        $this->postJson('/api/bookings', $this->booking(['booking_date' => '2026-11-04']))->assertUnprocessable();
        $this->postJson('/api/bookings', $this->booking(['booking_time' => '09:00']))->assertCreated();
        $this->assertSame($catalog, Shop::findOrFail(1)->settings['services']);
        $this->assertDatabaseHas('audit_events', ['shop_id' => 1, 'action' => 'shop.business_hours_saved']);
    }

    public function test_invalid_shapes_ranges_dates_and_stale_versions_are_rejected(): void
    {
        $this->authenticate();
        foreach ([['hours' => [1 => ['09:00', '16:00']]], ['hours' => array_fill(1, 7, ['16:00', '09:00'])],
            ['breaks' => [['12:00', '11:00']]], ['closed_dates' => ['not-a-date']], ['closed_dates' => ['2026-11-04', '2026-11-04']]] as $changes) {
            $this->putJson('/api/admin/management/business-hours', $this->schedule($changes))->assertUnprocessable();
        }
        $this->putJson('/api/admin/management/business-hours', $this->schedule())->assertOk()->assertJsonPath('version', 1);
        $this->putJson('/api/admin/management/business-hours', $this->schedule())->assertConflict();
        $this->assertDatabaseCount('audit_events', 1);
    }

    public function test_schedule_changes_protect_all_barbers_future_appointments_but_not_cancelled_ones(): void
    {
        $this->postJson('/api/bookings', $this->booking(['barber_id' => 2]))->assertCreated();
        $this->authenticate();
        $settings = Shop::findOrFail(1)->settings;
        $this->putJson('/api/admin/management/business-hours', $this->schedule(['hours' => array_fill(1, 7, ['11:00', '16:00'])]))->assertUnprocessable()->assertJsonValidationErrors('hours');
        $this->assertSame($settings, Shop::findOrFail(1)->settings);
        Booking::query()->update(['status' => 'cancelled']);
        $this->putJson('/api/admin/management/business-hours', $this->schedule(['hours' => array_fill(1, 7, ['11:00', '16:00'])]))->assertOk();
        $this->assertDatabaseHas('bookings', ['price' => '250.00', 'duration' => 50, 'status' => 'cancelled']);
    }

    public function test_in_progress_appointments_are_protected_without_blocking_safe_edits(): void
    {
        $this->postJson('/api/bookings', $this->booking())->assertCreated();
        Carbon::setTestNow(Carbon::parse('2026-11-03 10:20:00', 'Africa/Johannesburg'));
        $this->authenticate();
        $this->putJson('/api/admin/management/business-hours', $this->schedule(['hours' => array_fill(1, 7, ['09:00', '10:30'])]))->assertUnprocessable();
        $this->putJson('/api/admin/management/business-hours', $this->schedule())->assertOk();
        $this->putJson('/api/admin/management/barbers/1/schedule', $this->schedule(['version' => 1]))->assertOk();
        $this->putJson('/api/admin/management/barbers/1/schedule', $this->schedule(['version' => 2, 'breaks' => [['10:30', '11:00']]]))->assertUnprocessable();
    }

    public function test_closed_days_override_barber_hours_and_ended_bookings_do_not_block_changes(): void
    {
        $this->postJson('/api/bookings', $this->booking())->assertCreated();
        Carbon::setTestNow(Carbon::parse('2026-11-03 11:00:00', 'Africa/Johannesburg'));
        $this->authenticate();
        $this->putJson('/api/admin/management/business-hours', $this->schedule(['hours' => array_fill(1, 7, null)]))->assertOk();
        $this->getJson('/api/bookings/availability?service_id=2&barber_id=any&booking_date=2026-11-04')->assertOk()->assertJsonPath('available_times', []);
    }
}
