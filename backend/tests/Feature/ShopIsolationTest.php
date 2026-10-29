<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ShopIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function shop(string $slug = 'second-shop'): Shop
    {
        return Shop::create(['name' => 'Second Shop', 'slug' => $slug, 'timezone' => 'Africa/Johannesburg']);
    }

    private function authenticate(int $shop = 1, string $role = 'owner', ?int $barber = null): User
    {
        $user = User::factory()->create(['password' => 'Shop-Test-Password-123']);
        $this->grantShopRole($user, $role, $shop, $barber);
        app('auth')->forgetGuards();
        $this->withToken($user->createToken('test', ['admin'])->plainTextToken);

        return $user;
    }

    private function booking(string $slug = 'forge-and-fade', int $barber = 1): array
    {
        return $this->postJson('/api/bookings', ['shop' => $slug, 'service_id' => 2, 'barber_id' => $barber,
            'booking_date' => now()->next('Monday')->toDateString(), 'booking_time' => '10:00',
            'customer_name' => 'Private Customer', 'email' => 'private@example.com', 'phone' => '0712345678'])
            ->assertCreated()->json('booking');
    }

    public function test_shop_registration_creates_an_owner_without_platform_privileges(): void
    {
        $response = $this->postJson('/api/admin/register-shop', ['shop_name' => 'New Cuts', 'shop_slug' => 'new-cuts',
            'name' => 'Owner', 'email' => 'NEW@example.com', 'password' => 'Owner-Password-12345',
            'password_confirmation' => 'Owner-Password-12345', 'role' => 'platform_admin', 'is_platform_admin' => true, 'shop_id' => 1])
            ->assertCreated()->assertJsonPath('user.role', 'owner')->assertJsonPath('user.shop.slug', 'new-cuts');
        $this->assertFalse(User::where('email', 'new@example.com')->firstOrFail()->is_platform_admin);
        $this->withToken($response->json('token'))->getJson('/api/admin/bookings')->assertOk()->assertJsonPath('total', 0);
        $this->assertDatabaseHas('audit_events', ['action' => 'shop.registered']);
    }

    public function test_duplicate_or_invalid_shop_identifiers_cannot_partially_register_an_account(): void
    {
        $payload = ['shop_name' => 'New', 'shop_slug' => 'forge-and-fade', 'name' => 'Owner', 'email' => 'new@example.com',
            'password' => 'Owner-Password-12345', 'password_confirmation' => 'Owner-Password-12345'];
        $this->postJson('/api/admin/register-shop', $payload)->assertUnprocessable()->assertJsonValidationErrors('shop_slug');
        $this->postJson('/api/admin/register-shop', [...$payload, 'shop_slug' => '../private'])->assertUnprocessable();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('shops', 1);
    }

    public function test_two_shops_can_book_the_same_barber_id_and_time_without_conflicting(): void
    {
        $shop = $this->shop();
        $first = $this->booking();
        $second = $this->booking($shop->slug);
        $this->assertSame(1, $first['shop_id']);
        $this->assertSame($shop->id, $second['shop_id']);
        $this->assertDatabaseCount('shop_booking_day_locks', 2);
        $this->assertNotSame($first['reference'], $second['reference']);
    }

    public function test_availability_is_scoped_and_unknown_shops_never_fall_back(): void
    {
        $shop = $this->shop();
        $booking = $this->booking();
        $params = '?service_id=2&barber_id=1&booking_date='.$booking['booking_date'];
        $times = $this->getJson('/api/bookings/availability'.$params)->assertOk()->json('available_times');
        $this->assertNotContains('10:00', $times);
        $other = $this->getJson('/api/bookings/availability'.$params.'&shop='.$shop->slug)->assertOk()->json('available_times');
        $this->assertContains('10:00', $other);
        $this->getJson('/api/bookings/catalog?shop=missing-shop')->assertNotFound();
        $this->getJson('/api/bookings/catalog?shop[]=forge-and-fade')->assertUnprocessable();
    }

    public function test_owner_cannot_read_count_modify_or_select_another_shops_data(): void
    {
        $shop = $this->shop();
        $private = $this->booking($shop->slug);
        $this->authenticate();
        $this->getJson('/api/admin/bookings')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/admin/dashboard')->assertOk()->assertJsonPath('upcoming_count', 0)->assertJsonCount(0, 'next');
        $this->patchJson('/api/admin/bookings/'.$private['id'], ['action' => 'cancel', 'revision' => 0])->assertNotFound();
        $this->withHeader('X-Shop-Id', (string) $shop->id)->getJson('/api/admin/bookings')->assertForbidden();
        $this->assertDatabaseHas('bookings', ['id' => $private['id'], 'status' => 'confirmed']);
    }

    public function test_membership_is_required_even_with_a_legacy_admin_flag(): void
    {
        $user = User::factory()->create(['password' => 'Shop-Test-Password-123']);
        $user->forceFill(['is_admin' => true])->save();
        $this->postJson('/api/admin/login', ['email' => $user->email, 'password' => 'Shop-Test-Password-123'])->assertUnprocessable();
        $this->withToken($user->createToken('orphan', ['admin'])->plainTextToken)->getJson('/api/admin/bookings')->assertForbidden();
    }

    public function test_barber_only_sees_assigned_bookings_and_cannot_mutate_or_invite(): void
    {
        $own = $this->booking('forge-and-fade', 1);
        $this->booking('forge-and-fade', 2);
        $this->authenticate(1, 'barber', 1);
        $this->getJson('/api/admin/bookings')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $own['id']);
        $this->getJson('/api/admin/dashboard')->assertOk()->assertJsonPath('upcoming_count', 1);
        $this->patchJson('/api/admin/bookings/'.$own['id'], ['action' => 'cancel', 'revision' => 0])->assertForbidden();
        $this->getJson('/api/admin/invitations')->assertForbidden();
        $this->postJson('/api/admin/invitations', ['email' => 'member@example.com', 'send_email' => false])->assertForbidden();
        $this->getJson('/api/admin/profile')->assertOk()->assertJsonPath('role', 'barber');
    }

    public function test_manager_can_manage_appointments_but_not_members_or_shop_settings(): void
    {
        $own = $this->booking();
        $this->authenticate(1, 'manager');
        $this->patchJson('/api/admin/bookings/'.$own['id'], ['action' => 'cancel', 'revision' => 0])->assertOk();
        $this->getJson('/api/admin/invitations')->assertForbidden();
        $this->patchJson('/api/admin/shop', ['name' => 'Changed'])->assertForbidden();
    }

    public function test_invitation_carries_the_owners_shop_and_restricted_role_not_client_claims(): void
    {
        $shop = $this->shop();
        $this->authenticate($shop->id);
        $response = $this->postJson('/api/admin/invitations', ['email' => 'barber@example.com', 'send_email' => false,
            'role' => 'barber', 'barber_id' => 2, 'shop_id' => 1])->assertCreated();
        parse_str(explode('?', parse_url($response->json('link'), PHP_URL_FRAGMENT), 2)[1], $query);
        $created = $this->postJson('/api/admin/register', ['name' => 'Barber', 'email' => $query['email'], 'invitation' => $query['invitation'],
            'password' => 'Barber-Password-123', 'password_confirmation' => 'Barber-Password-123', 'role' => 'owner', 'shop_id' => 1])
            ->assertCreated()->assertJsonPath('user.role', 'barber')->assertJsonPath('user.shop.id', $shop->id);
        $this->assertDatabaseHas('shop_memberships', ['user_id' => User::where('email', 'barber@example.com')->firstOrFail()->id,
            'shop_id' => $shop->id, 'role' => 'barber', 'barber_id' => 2]);
        $this->assertNotEmpty($created->json('token'));
    }

    public function test_platform_role_and_unassigned_barbers_cannot_be_invited(): void
    {
        $this->authenticate();
        $this->postJson('/api/admin/invitations', ['email' => 'member@example.com', 'send_email' => false, 'role' => 'platform_admin'])
            ->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->postJson('/api/admin/invitations', ['email' => 'member@example.com', 'send_email' => false, 'role' => 'barber'])
            ->assertUnprocessable()->assertJsonValidationErrors('barber_id');
        $this->assertDatabaseCount('admin_invitations', 0);
    }

    public function test_invitation_history_and_contacts_are_attributed_to_the_selected_shop(): void
    {
        $shop = $this->shop();
        DB::table('admin_invitations')->insert(['shop_id' => $shop->id, 'email' => 'private@example.com', 'token_hash' => str_repeat('a', 64), 'expires_at' => now()->addDay()]);
        $this->authenticate();
        $this->getJson('/api/admin/invitations')->assertOk()->assertJsonCount(0, 'invitations');
        $this->postJson('/api/contact', ['shop' => $shop->slug, 'shop_id' => 1, 'name' => 'Guest', 'email' => 'guest@example.com', 'message' => 'Hello'])
            ->assertCreated();
        $this->assertDatabaseHas('contact_messages', ['shop_id' => $shop->id, 'email' => 'guest@example.com']);
    }

    public function test_revocation_takes_effect_on_the_next_request_but_logout_still_works(): void
    {
        $user = $this->authenticate();
        DB::table('shop_memberships')->where('user_id', $user->id)->delete();
        $this->getJson('/api/admin/bookings')->assertForbidden();
        $this->postJson('/api/admin/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_owner_can_edit_business_details_but_not_slug_timezone_or_ownership(): void
    {
        $user = $this->authenticate();
        $this->patchJson('/api/admin/shop', ['name' => 'Updated Shop', 'address' => 'Cape Town', 'phone' => '0712345678',
            'slug' => 'hijacked', 'timezone' => 'UTC', 'owner_id' => 999])->assertOk()
            ->assertJsonPath('name', 'Updated Shop')->assertJsonPath('slug', 'forge-and-fade')->assertJsonPath('timezone', 'Africa/Johannesburg');
        $this->assertDatabaseHas('shop_memberships', ['user_id' => $user->id, 'role' => 'owner']);
        $this->assertDatabaseHas('audit_events', ['shop_id' => 1, 'user_id' => $user->id, 'action' => 'shop.updated']);
    }

    public function test_a_role_in_one_shop_never_grants_the_same_role_in_another(): void
    {
        $shop = $this->shop();
        $own = $this->booking($shop->slug, 2);
        $this->booking($shop->slug, 1);
        $user = $this->authenticate();
        $this->grantShopRole($user, 'barber', $shop->id, 2);
        $this->withHeader('X-Shop-Id', (string) $shop->id)->getJson('/api/admin/profile')->assertOk()->assertJsonPath('role', 'barber');
        $this->getJson('/api/admin/bookings')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $own['id']);
        $this->patchJson('/api/admin/bookings/'.$own['id'], ['action' => 'cancel', 'revision' => 0])->assertForbidden();
        $this->getJson('/api/admin/invitations')->assertForbidden();
    }

    public function test_platform_access_is_audited_console_only_and_revokes_old_tokens(): void
    {
        $shop = $this->shop();
        $this->booking($shop->slug);
        $user = $this->authenticate();
        $this->artisan('admin:platform', ['email' => $user->email])->assertSuccessful();
        $this->assertTrue($user->fresh()->is_platform_admin);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseHas('audit_events', ['user_id' => $user->id, 'action' => 'platform.access.granted']);
        app('auth')->forgetGuards();
        $token = $this->postJson('/api/admin/login', ['email' => $user->email, 'password' => 'Shop-Test-Password-123'])
            ->assertOk()->assertJsonPath('user.role', 'platform_admin')->json('token');
        $this->withToken($token)->withHeader('X-Shop-Id', (string) $shop->id)->getJson('/api/admin/bookings')->assertOk()->assertJsonPath('total', 1);
    }
}
