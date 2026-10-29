<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardStatisticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-02 10:00:00', 'Africa/Johannesburg'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function member(string $role = 'owner'): void
    {
        $user = User::factory()->create();
        $this->grantShopRole($user, $role, 1, $role === 'barber' ? 1 : null);
        $this->withToken($user->createToken('dashboard-test', ['admin'])->plainTextToken);
    }

    private function booking(array $changes = []): Booking
    {
        return Booking::create($changes + ['shop_id' => 1, 'service_id' => 1, 'service_name' => 'Saved Service',
            'barber_id' => 1, 'barber_name' => 'Saved Barber', 'booking_date' => '2026-11-02',
            'booking_time' => '11:00', 'duration' => 30, 'customer_name' => 'Statistics Customer',
            'email' => 'statistics@example.com', 'phone' => '0712345678', 'status' => 'confirmed',
            'price' => '220.10', 'currency' => 'ZAR']);
    }

    public function test_period_uses_saved_prices_excludes_cancellations_and_reports_unknown_prices(): void
    {
        $this->member();
        $this->booking();
        $this->booking(['booking_date' => '2026-10-27', 'price' => '10.20']);
        $this->booking(['price' => null]);
        $this->booking(['status' => 'cancelled', 'price' => '999.00']);
        $this->booking(['booking_date' => '2026-10-26']);
        $this->booking(['booking_date' => '2026-11-03']);
        config(['booking.services.1.price' => 9999]);
        $response = $this->getJson('/api/admin/dashboard?days=7')->assertOk()
            ->assertJsonPath('statistics.from', '2026-10-27')->assertJsonPath('statistics.to', '2026-11-02')
            ->assertJsonPath('statistics.confirmed_count', 3)->assertJsonPath('statistics.cancelled_count', 1)
            ->assertJsonPath('statistics.unpriced_count', 1)->assertJsonPath('statistics.booked_values.0.booking_count', 2);
        $this->assertEquals('230.30', $response->json('statistics.booked_values.0.amount'));
    }

    public function test_currencies_and_other_shops_are_never_combined(): void
    {
        $this->member('manager');
        $this->booking();
        $this->booking(['price' => '12.50', 'currency' => 'USD']);
        $shop = Shop::create(['name' => 'Foreign Shop', 'slug' => 'foreign-statistics', 'timezone' => 'Africa/Johannesburg']);
        $this->booking(['shop_id' => $shop->id, 'price' => '9999.00']);
        $response = $this->getJson('/api/admin/dashboard')->assertOk()->assertJsonPath('statistics.confirmed_count', 2)
            ->assertJsonCount(2, 'statistics.booked_values');
        $amounts = collect($response->json('statistics.booked_values'))->pluck('amount', 'currency');
        $this->assertEquals('220.10', $amounts['ZAR']);
        $this->assertEquals('12.50', $amounts['USD']);
    }

    public function test_barbers_only_get_their_own_counts_and_no_financial_summary(): void
    {
        $this->member('barber');
        $this->booking();
        $this->booking(['barber_id' => 2]);
        $this->getJson('/api/admin/dashboard')->assertOk()->assertJsonPath('today_count', 1)
            ->assertJsonPath('statistics.confirmed_count', 1)->assertJsonPath('statistics.booked_values', null)
            ->assertJsonPath('statistics.unpriced_count', null);
    }

    public function test_today_and_start_time_boundaries_use_the_shop_timezone(): void
    {
        $this->member();
        Carbon::setTestNow(Carbon::parse('2026-11-02 22:30:00', 'UTC'));
        $this->booking(['booking_date' => '2026-11-03', 'booking_time' => '00:20']);
        $this->booking(['booking_date' => '2026-11-03', 'booking_time' => '00:30']);
        $this->booking(['booking_date' => '2026-11-03', 'booking_time' => '00:40']);
        $this->getJson('/api/admin/dashboard')->assertOk()->assertJsonPath('today', '2026-11-03')
            ->assertJsonPath('timezone', 'Africa/Johannesburg')->assertJsonPath('today_count', 3)
            ->assertJsonPath('past_count', 1)->assertJsonPath('upcoming_count', 2)->assertJsonCount(2, 'next');
    }

    public function test_empty_statistics_and_period_validation(): void
    {
        $this->member();
        $this->getJson('/api/admin/dashboard')->assertOk()->assertJsonPath('statistics.days', 30)
            ->assertJsonPath('statistics.confirmed_count', 0)->assertJsonPath('statistics.unpriced_count', 0)
            ->assertJsonCount(0, 'statistics.booked_values');
        $this->getJson('/api/admin/dashboard?days=90')->assertOk()->assertJsonPath('statistics.days', 90);
        $this->getJson('/api/admin/dashboard?days=365')->assertUnprocessable();
        $this->getJson('/api/admin/dashboard?days[]=7')->assertUnprocessable();
    }
}
