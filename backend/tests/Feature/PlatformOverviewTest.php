<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformOverviewTest extends TestCase
{
    use RefreshDatabase;

    private function authenticate(bool $platform = true, bool $admin = true, array $abilities = ['admin']): User
    {
        $user = User::factory()->create(['is_admin' => $admin, 'is_platform_admin' => $platform]);
        app('auth')->forgetGuards();
        $this->withToken($user->createToken('platform-test', $abilities)->plainTextToken);

        return $user;
    }

    private function booking(int $shop, string $status = 'confirmed'): void
    {
        Booking::create(['shop_id' => $shop, 'service_id' => 1, 'service_name' => 'Private haircut', 'barber_id' => 1,
            'barber_name' => 'Private barber', 'booking_date' => '2099-11-03', 'booking_time' => '10:00', 'duration' => 30,
            'customer_name' => 'Private Customer', 'email' => 'private@example.com', 'phone' => '0712345678',
            'notes' => 'Private note', 'status' => $status, 'price' => 175, 'currency' => 'ZAR']);
    }

    public function test_anonymous_customers_and_shop_staff_cannot_read_platform_data(): void
    {
        $this->getJson('/api/platform/overview')->assertUnauthorized();
        $this->authenticate(false, false);
        $this->getJson('/api/platform/overview')->assertForbidden()->assertJsonPath('message', 'Platform administrator access is required.');
        foreach (['owner', 'manager', 'barber'] as $role) {
            $user = $this->authenticate(false);
            $this->grantShopRole($user, $role, 1, $role === 'barber' ? 1 : null);
            $this->withHeaders(['X-Shop-Id' => '1', 'X-Role' => 'platform_admin'])
                ->getJson('/api/platform/overview?is_platform_admin=1&role=platform_admin')->assertForbidden();
        }
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_platform_flag_requires_admin_enabled_account_and_admin_token_ability(): void
    {
        $this->authenticate(true, false);
        $this->getJson('/api/platform/overview')->assertForbidden();
        $this->authenticate(true, true, ['customer']);
        $this->getJson('/api/platform/overview')->assertForbidden();
    }

    public function test_platform_totals_span_shops_without_exposing_private_records(): void
    {
        $other = Shop::create(['name' => 'Other Shop', 'slug' => 'platform-other', 'address' => 'Private address', 'phone' => '0712345678',
            'settings' => ['services' => [1 => ['name' => 'Active', 'active' => true], 2 => ['name' => 'Inactive', 'active' => false]],
                'barbers' => [1 => ['name' => 'Active', 'active' => true]]]]);
        $this->booking(1);
        $this->booking($other->id);
        $this->booking($other->id, 'cancelled');
        $member = User::factory()->create();
        $this->grantShopRole($member, 'owner', $other->id);
        $revoked = User::factory()->create();
        $this->grantShopRole($revoked, 'revoked', $other->id);
        $user = $this->authenticate();
        $response = $this->withHeader('X-Shop-Id', '1')->getJson('/api/platform/overview')->assertOk()
            ->assertJsonPath('scope', 'platform')->assertJsonPath('summary.shops', 2)->assertJsonPath('summary.bookings', 3)
            ->assertJsonPath('summary.memberships', 1)->assertJsonPath('shops.total', 2)
            ->assertJsonPath('shops.data.0.id', $other->id)->assertJsonPath('shops.data.0.booking_count', 2)
            ->assertJsonPath('shops.data.0.confirmed_count', 1)->assertJsonPath('shops.data.0.active_services', 1)
            ->assertJsonPath('shops.data.0.active_barbers', 1)->assertJsonPath('shops.data.0.membership_count', 1);
        foreach (['settings', 'address', 'phone', 'email', 'members', 'bookings', 'payments'] as $field) {
            $response->assertJsonMissingPath('shops.data.0.'.$field);
        }
        $this->assertStringNotContainsString('Private Customer', $response->getContent());
        $this->assertStringNotContainsString('private@example.com', $response->getContent());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertDatabaseHas('audit_events', ['shop_id' => null, 'user_id' => $user->id, 'action' => 'platform.overview_viewed']);
    }

    public function test_search_pagination_empty_results_and_validation(): void
    {
        for ($index = 1; $index <= 22; $index++) {
            Shop::create(['name' => 'Platform Test '.$index, 'slug' => 'platform-test-'.$index]);
        }
        $this->authenticate();
        $this->getJson('/api/platform/overview?search=platform-test&page=2')->assertOk()
            ->assertJsonPath('shops.total', 22)->assertJsonPath('shops.current_page', 2)->assertJsonCount(2, 'shops.data')
            ->assertJsonPath('summary.shops', 23);
        $this->getJson('/api/platform/overview?search=missing-shop')->assertOk()->assertJsonCount(0, 'shops.data');
        $this->getJson('/api/platform/overview?search=0')->assertOk()->assertJsonPath('shops.total', 2);
        $this->getJson('/api/platform/overview?search[]=bad')->assertUnprocessable();
        $this->getJson('/api/platform/overview?page=0')->assertUnprocessable();
    }

    public function test_revoking_platform_flag_denies_an_existing_token_on_next_request(): void
    {
        $user = $this->authenticate();
        $this->getJson('/api/platform/overview')->assertOk();
        $user->forceFill(['is_platform_admin' => false])->save();
        app('auth')->forgetGuards();
        $this->getJson('/api/platform/overview')->assertForbidden();
    }

    public function test_reads_cannot_change_shop_or_account_roles_and_are_rate_limited(): void
    {
        $user = $this->authenticate();
        $before = Shop::findOrFail(1)->toJson();
        $this->postJson('/api/platform/overview', ['status' => 'suspended'])->assertStatus(405);
        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->getJson('/api/platform/overview?status=suspended')->assertOk();
        }
        $this->getJson('/api/platform/overview')->assertStatus(429);
        $this->assertSame($before, Shop::findOrFail(1)->toJson());
        $this->assertTrue($user->fresh()->is_platform_admin);
        $this->assertSame(1, User::where('is_platform_admin', true)->count());
    }
}
