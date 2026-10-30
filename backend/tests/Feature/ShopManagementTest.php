<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShopManagementTest extends TestCase
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
        app('auth')->forgetGuards();
        $this->withToken($user->createToken('management-test', ['admin'])->plainTextToken);

        return $user;
    }

    private function service(array $changes = []): array
    {
        return $changes + ['version' => 0, 'name' => 'Updated Service', 'price' => '275.50', 'duration' => 60, 'active' => true];
    }

    private function booking(array $changes = []): array
    {
        return $changes + ['service_id' => 2, 'barber_id' => 1, 'booking_date' => '2026-11-03', 'booking_time' => '10:00',
            'customer_name' => 'Management QA', 'email' => 'management@example.com', 'phone' => '0712345678'];
    }

    private function schedule(array $changes = []): array
    {
        return $changes + ['version' => 0, 'hours' => array_replace(array_fill(1, 7, null), [2 => ['10:00', '16:00']]),
            'breaks' => [['12:00', '12:30']], 'closed_dates' => ['2026-11-04']];
    }

    public function test_management_is_owner_only_and_never_cross_shop(): void
    {
        $this->getJson('/api/admin/management')->assertUnauthorized();
        $this->member('manager');
        $this->getJson('/api/admin/management')->assertForbidden();
        $this->putJson('/api/admin/management/services/2', $this->service())->assertForbidden();
        $this->member('barber');
        $this->getJson('/api/admin/management')->assertForbidden();
        $this->member();
        $shop = Shop::create(['name' => 'Other', 'slug' => 'management-other', 'timezone' => 'Africa/Johannesburg']);
        $this->withHeader('X-Shop-Id', $shop->id)->getJson('/api/admin/management')->assertForbidden();
    }

    public function test_prices_durations_and_names_change_only_for_new_bookings_in_this_shop(): void
    {
        $this->member();
        $old = $this->postJson('/api/bookings', $this->booking())->assertCreated()->json('booking');
        $this->putJson('/api/admin/management/services/2', $this->service())->assertOk()->assertJsonPath('version', 1);
        $this->getJson('/api/bookings/catalog')->assertOk()->assertJsonPath('services.1.price', '275.50')->assertJsonPath('services.1.duration', 60);
        $this->postJson('/api/bookings', $this->booking(['booking_time' => '11:00', 'quoted_price' => 250]))->assertUnprocessable();
        $this->postJson('/api/bookings', $this->booking(['booking_time' => '11:00']))->assertCreated()
            ->assertJsonPath('booking.price', '275.50')->assertJsonPath('booking.duration', 60)->assertJsonPath('booking.service_name', 'Updated Service');
        $this->assertDatabaseHas('bookings', ['id' => $old['id'], 'price' => '250.00', 'duration' => 50, 'service_name' => 'Skin Fade']);
        Shop::create(['name' => 'Independent', 'slug' => 'independent-catalog', 'timezone' => 'Africa/Johannesburg']);
        $this->getJson('/api/bookings/catalog?shop=independent-catalog')->assertOk()->assertJsonCount(0, 'services')->assertJsonCount(0, 'barbers');
    }

    public function test_catalog_creation_deactivation_validation_and_stale_writes(): void
    {
        $this->member();
        $this->postJson('/api/admin/management/services', $this->service())->assertOk()->assertJsonCount(7, 'services');
        $this->putJson('/api/admin/management/services/2', $this->service())->assertConflict();
        $this->putJson('/api/admin/management/services/2', $this->service(['version' => 1, 'price' => '1.234']))->assertUnprocessable();
        $this->putJson('/api/admin/management/services/2', $this->service(['version' => 1, 'duration' => 0]))->assertUnprocessable();
        $this->putJson('/api/admin/management/services/2', $this->service(['version' => 1, 'active' => false]))->assertOk();
        $this->postJson('/api/bookings', $this->booking())->assertUnprocessable();
        $this->postJson('/api/admin/management/barbers', ['version' => 2, 'name' => 'New Barber', 'active' => true])->assertOk()->assertJsonCount(4, 'barbers');
        $this->postJson('/api/admin/bookings', $this->booking(['service_id' => 7, 'barber_id' => 4]))->assertCreated()->assertJsonPath('booking.barber_name', 'New Barber');
        $this->putJson('/api/admin/management/barbers/4', ['version' => 3, 'name' => 'New Barber', 'active' => false])->assertOk();
        $this->getJson('/api/bookings/availability?service_id=7&barber_id=4&booking_date=2026-11-04')->assertUnprocessable();
    }

    public function test_staff_hours_breaks_days_off_and_shop_hours_are_enforced(): void
    {
        $this->member();
        $this->putJson('/api/admin/management/barbers/1/schedule', $this->schedule())->assertOk();
        foreach ([['booking_time' => '08:00'], ['booking_time' => '11:30'], ['booking_time' => '13:00'], ['booking_time' => '15:30'], ['booking_date' => '2026-11-04']] as $changes) {
            $this->postJson('/api/bookings', $this->booking($changes))->assertUnprocessable();
        }
        $this->postJson('/api/admin/bookings', $this->booking())->assertCreated();
        $this->getJson('/api/bookings/availability?service_id=2&barber_id=1&booking_date=2026-11-03')->assertOk()->assertJsonMissing(['13:00']);
    }

    public function test_schedule_changes_cannot_invalidate_existing_future_appointments(): void
    {
        $this->member();
        $booking = $this->postJson('/api/bookings', $this->booking())->assertCreated()->json('booking');
        $this->putJson('/api/admin/management/barbers/1/schedule', $this->schedule(['hours' => array_fill(1, 7, null)]))->assertUnprocessable();
        $this->getJson('/api/admin/management')->assertOk()->assertJsonPath('version', 0);
        $this->assertDatabaseHas('bookings', ['id' => $booking['id'], 'status' => 'confirmed']);
        $this->putJson('/api/admin/management/barbers/1/schedule', $this->schedule(['hours' => array_replace(array_fill(1, 7, null), [2 => ['16:00', '10:00']])]))->assertUnprocessable();
        $this->putJson('/api/admin/management/barbers/1/schedule', $this->schedule(['closed_dates' => ['not-a-date']]))->assertUnprocessable();
        $this->putJson('/api/admin/management/barbers/1/schedule', $this->schedule(['breaks' => [['start' => '10:00', 'end' => '11:00']]]))->assertUnprocessable();
    }

    public function test_staff_roles_assignments_and_revocation_preserve_owner_and_account(): void
    {
        $owner = $this->member();
        $staff = User::factory()->create();
        $this->grantShopRole($staff, 'manager');
        $membership = DB::table('shop_memberships')->where('user_id', $staff->id)->value('id');
        $ownerMembership = DB::table('shop_memberships')->where('user_id', $owner->id)->value('id');
        $token = $staff->createToken('staff-test', ['admin'])->plainTextToken;
        $this->putJson('/api/admin/management/members/'.$membership, ['version' => 0, 'role' => 'owner'])->assertUnprocessable();
        $this->deleteJson('/api/admin/management/members/'.$ownerMembership, ['version' => 0])->assertForbidden();
        $this->putJson('/api/admin/management/members/'.$membership, ['version' => 0, 'role' => 'barber', 'barber_id' => 1])->assertOk();
        $this->deleteJson('/api/admin/management/members/'.$membership, ['version' => 1])->assertOk();
        $this->assertDatabaseHas('users', ['id' => $staff->id]);
        $this->assertDatabaseHas('shop_memberships', ['id' => $membership, 'role' => 'revoked']);
        $other = Shop::create(['name' => 'Other Membership', 'slug' => 'restore-other', 'timezone' => 'Africa/Johannesburg']);
        $this->grantShopRole($staff, 'manager', $other->id);
        app('auth')->forgetGuards();
        $this->withToken($token)->withHeader('X-Shop-Id', '1')->getJson('/api/admin/bookings')->assertForbidden();
        $this->withHeader('X-Shop-Id', (string) $other->id)->getJson('/api/admin/bookings')->assertOk();
        app('auth')->forgetGuards();
        $this->withToken($owner->createToken('restore-test', ['admin'])->plainTextToken)->withHeader('X-Shop-Id', '1')
            ->putJson('/api/admin/management/members/'.$membership, ['version' => 2, 'role' => 'manager'])->assertOk();
        app('auth')->forgetGuards();
        $this->withToken($token)->getJson('/api/admin/bookings')->assertOk();
    }
}
