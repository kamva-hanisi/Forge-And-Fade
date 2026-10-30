<?php

namespace Tests\Feature;

use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShopOnboardingTest extends TestCase
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

    private function register(): array
    {
        return $this->postJson('/api/admin/register-shop', [
            'shop_name' => 'Fresh Cuts', 'shop_slug' => 'fresh-cuts', 'name' => 'Test Owner', 'email' => 'onboarding@example.com',
            'password' => 'Onboarding-Test-12345', 'password_confirmation' => 'Onboarding-Test-12345',
            'settings' => ['services' => config('booking.services'), 'barbers' => config('booking.barbers')],
        ])->assertCreated()->json();
    }

    public function test_new_shop_is_empty_and_cannot_book_demo_catalog_ids(): void
    {
        $this->register();
        $this->getJson('/api/bookings/catalog?shop=fresh-cuts')->assertOk()->assertJsonCount(0, 'services')->assertJsonCount(0, 'barbers');
        $this->postJson('/api/bookings', ['shop' => 'fresh-cuts', 'service_id' => 2, 'barber_id' => 1,
            'booking_date' => '2026-11-03', 'booking_time' => '10:00', 'customer_name' => 'Customer',
            'email' => 'customer@example.com', 'phone' => '0712345678'])->assertUnprocessable()->assertJsonValidationErrors(['service_id', 'barber_id']);
        $this->assertDatabaseCount('bookings', 0);
        $this->getJson('/api/bookings/catalog')->assertOk()->assertJsonCount(6, 'services')->assertJsonCount(3, 'barbers');
    }

    public function test_owner_can_build_an_independent_catalog_then_accept_real_bookings(): void
    {
        $registered = $this->register();
        $this->withToken($registered['token'])->withHeader('X-Shop-Id', $registered['user']['shop']['id']);
        $this->getJson('/api/admin/management')->assertOk()->assertJsonCount(0, 'services')->assertJsonCount(0, 'barbers');
        $this->postJson('/api/admin/management/services', ['version' => 0, 'name' => 'Fresh haircut', 'duration' => 30, 'price' => 175, 'active' => true])->assertOk()->assertJsonPath('services.0.id', 1);
        $this->postJson('/api/admin/management/barbers', ['version' => 1, 'name' => 'Real barber', 'active' => true])->assertOk()->assertJsonPath('barbers.0.id', 1);
        $this->postJson('/api/bookings', ['shop' => 'fresh-cuts', 'service_id' => 1, 'barber_id' => 'any',
            'booking_date' => '2026-11-03', 'booking_time' => '10:00', 'customer_name' => 'Customer',
            'email' => 'customer@example.com', 'phone' => '0712345678'])->assertCreated()
            ->assertJsonPath('booking.service_name', 'Fresh haircut')->assertJsonPath('booking.barber_name', 'Real barber')->assertJsonPath('booking.price', '175.00');
        $this->getJson('/api/bookings/catalog')->assertOk()->assertJsonPath('services.0.name', 'Classic Haircut')->assertJsonPath('services.0.price', 220);
    }

    public function test_snapshot_migration_preserves_existing_settings_and_explicit_empty_catalogs(): void
    {
        $legacy = Shop::create(['name' => 'Legacy', 'slug' => 'legacy-shop', 'settings' => ['closed_dates' => ['2026-12-25']]]);
        $custom = Shop::create(['name' => 'Custom', 'slug' => 'custom-shop', 'settings' => ['services' => [], 'barbers' => [], 'hours' => []]]);
        $migration = require database_path('migrations/2026_10_30_000001_snapshot_shop_catalogs.php');
        $migration->up();
        $this->assertCount(6, $legacy->fresh()->settings['services']);
        $this->assertCount(3, $legacy->fresh()->settings['barbers']);
        $this->assertSame(['2026-12-25'], $legacy->fresh()->settings['closed_dates']);
        $this->assertSame([], $custom->fresh()->settings['services']);
        $this->assertSame([], $custom->fresh()->settings['barbers']);
        $this->assertSame([], $custom->fresh()->settings['hours']);
        $snapshot = DB::table('shops')->where('id', $legacy->id)->value('settings');
        config(['booking.services' => [], 'booking.barbers' => []]);
        $migration->up();
        $this->assertSame($snapshot, DB::table('shops')->where('id', $legacy->id)->value('settings'));
    }

    public function test_uninitialized_nondefault_shop_never_inherits_demo_staff(): void
    {
        Shop::create(['name' => 'Empty', 'slug' => 'empty-shop']);
        $this->getJson('/api/bookings/catalog?shop=empty-shop')->assertOk()->assertJsonCount(0, 'services')->assertJsonCount(0, 'barbers');
    }
}
