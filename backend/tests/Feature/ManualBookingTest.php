<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ManualBookingTest extends TestCase
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

    private function member(string $role = 'owner', int $shop = 1): User
    {
        $user = User::factory()->create();
        $this->grantShopRole($user, $role, $shop, $role === 'barber' ? 1 : null);
        $this->withToken($user->createToken('manual-test', ['admin'])->plainTextToken);

        return $user;
    }

    private function input(array $changes = []): array
    {
        return $changes + ['service_id' => 2, 'barber_id' => 1, 'booking_date' => '2026-11-03', 'booking_time' => '10:00',
            'customer_name' => 'Manual Booking Customer', 'email' => 'manual@example.com', 'phone' => '0712345678'];
    }

    public function test_owner_creates_a_trusted_priced_booking_and_audit_record(): void
    {
        $user = $this->member();
        $response = $this->postJson('/api/admin/bookings', $this->input(['price' => 1, 'duration' => 1, 'status' => 'cancelled', 'shop_id' => 999]))
            ->assertCreated()->assertJsonPath('booking.price', '250.00')->assertJsonPath('booking.duration', 50)
            ->assertJsonPath('booking.status', 'confirmed')->assertJsonPath('booking.shop_id', 1);
        $this->assertMatchesRegularExpression('/^FF-[A-Z0-9]{16}$/', $response->json('booking.reference'));
        $this->assertDatabaseHas('audit_events', ['user_id' => $user->id, 'action' => 'booking.created_by_staff', 'subject_id' => $response->json('booking.id')]);
        $this->getJson('/api/admin/bookings/catalog')->assertOk()->assertJsonPath('shop.slug', 'forge-and-fade');
        $this->getJson('/api/admin/bookings/availability?service_id=2&barber_id=1&booking_date=2026-11-03')->assertOk()->assertJsonMissing(['10:00']);
    }

    public function test_anonymous_ordinary_and_barber_users_cannot_create(): void
    {
        $this->postJson('/api/admin/bookings', $this->input())->assertUnauthorized();
        $user = User::factory()->create();
        $this->withToken($user->createToken('ordinary', ['admin'])->plainTextToken);
        $this->postJson('/api/admin/bookings', $this->input())->assertForbidden();
        $this->member('barber');
        $this->postJson('/api/admin/bookings', $this->input())->assertForbidden();
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_public_and_staff_bookings_share_overlap_checks_and_cancelled_slot_reuse(): void
    {
        $this->member();
        $booking = $this->postJson('/api/bookings', $this->input())->assertCreated()->json('booking');
        $this->postJson('/api/admin/bookings', $this->input(['booking_time' => '10:20']))->assertConflict();
        $this->patchJson('/api/admin/bookings/'.$booking['id'], ['action' => 'cancel', 'revision' => 0])->assertOk();
        $this->postJson('/api/admin/bookings', $this->input())->assertCreated();
        $this->postJson('/api/bookings', $this->input())->assertConflict();
        $this->assertDatabaseCount('bookings', 2);
    }

    public function test_managers_cannot_create_in_another_shop_or_override_its_schedule(): void
    {
        $shop = Shop::create(['name' => 'Private Shop', 'slug' => 'private-manual', 'timezone' => 'Africa/Johannesburg',
            'settings' => ['closed_dates' => ['2026-11-03'], 'services' => config('booking.services'),
                'barbers' => array_map(fn ($name) => ['name' => $name, 'active' => true], config('booking.barbers'))]]);
        $this->member('manager', $shop->id);
        $this->getJson('/api/admin/bookings/catalog?shop=forge-and-fade')->assertOk()->assertJsonPath('shop.slug', 'private-manual');
        $this->postJson('/api/admin/bookings', $this->input(['shop' => 'forge-and-fade', 'shop_id' => 1]))->assertUnprocessable();
        $this->postJson('/api/admin/bookings', $this->input(['booking_date' => '2026-11-04', 'shop_id' => 1]))->assertCreated()->assertJsonPath('booking.shop_id', $shop->id);
        $this->withHeader('X-Shop-Id', 1)->postJson('/api/admin/bookings', $this->input())->assertForbidden();
    }

    public function test_staff_cannot_bypass_hours_breaks_past_dates_or_catalog_validation(): void
    {
        $this->member();
        foreach ([['booking_time' => '07:00'], ['booking_time' => '13:00'], ['booking_date' => '2026-11-01'],
            ['booking_date' => '2026-11-02', 'booking_time' => '08:00'], ['service_id' => 999], ['barber_id' => 999], ['email' => 'invalid']] as $changes) {
            $this->postJson('/api/admin/bookings', $this->input($changes))->assertUnprocessable();
        }
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_price_changes_require_review_and_creation_is_rate_limited(): void
    {
        $this->member();
        $this->postJson('/api/admin/bookings', $this->input(['quoted_price' => 1]))->assertUnprocessable()->assertJsonValidationErrors('quoted_price');
        for ($attempt = 0; $attempt < 9; $attempt++) {
            $this->postJson('/api/admin/bookings', [])->assertUnprocessable();
        }
        $this->postJson('/api/admin/bookings', $this->input())->assertTooManyRequests();
        $this->assertDatabaseCount('bookings', 0);
    }
}
