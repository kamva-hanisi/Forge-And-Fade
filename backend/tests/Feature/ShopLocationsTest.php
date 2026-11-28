<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopLocationsTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(string $role): void
    {
        $user = User::factory()->create();
        $this->grantShopRole($user, $role);
        app('auth')->forgetGuards();
        $this->withToken($user->createToken('location-test', ['admin'])->plainTextToken);
    }

    public function test_directory_lists_active_shops_using_their_own_hours_and_public_details(): void
    {
        $this->freezeTime();
        $today = now('Africa/Johannesburg')->startOfDay();
        $tomorrow = $today->copy()->addDay()->toDateString();
        $studio = Shop::create([
            'name' => 'A Studio', 'slug' => 'a-studio', 'timezone' => 'Africa/Johannesburg',
            'address' => '10 Example Street, Cape Town, 8001', 'phone' => '+27 21 555 0100',
            'settings' => [
                'hours' => [2 => ['10:00', '14:00']],
                'closed_dates' => [$tomorrow, $today->copy()->subDay()->toDateString()],
                'directions_url' => 'https://maps.app.goo.gl/somePin',
                'management_revision' => 7,
            ],
        ]);
        $hidden = Shop::create(['name' => 'Hidden Shop', 'slug' => 'hidden-shop']);
        $hidden->forceFill(['status' => 'suspended'])->save();

        $this->getJson('/api/locations')->assertOk()->assertJsonCount(2, 'locations')
            ->assertJsonPath('locations.0.slug', $studio->slug)
            ->assertJsonPath('locations.0.address', $studio->address)
            ->assertJsonPath('locations.0.phone', $studio->phone)
            ->assertJsonPath('locations.0.timezone', $studio->timezone)
            ->assertJsonPath('locations.0.hours.2', ['10:00', '14:00'])
            ->assertJsonPath('locations.0.upcoming_closures', [$tomorrow])
            ->assertJsonPath('locations.0.directions_url', 'https://maps.app.goo.gl/somePin')
            ->assertJsonPath('locations.0.can_book', false)
            ->assertJsonPath('locations.1.can_book', true)
            ->assertJsonMissingPath('locations.0.settings');

        $this->getJson('/api/locations?shop='.$studio->slug)->assertOk()->assertJsonCount(1, 'locations')
            ->assertJsonPath('locations.0.address', $studio->address)
            ->assertJsonPath('locations.0.directions_url', 'https://maps.app.goo.gl/somePin');
        $this->getJson('/api/locations?shop=missing-location')->assertOk()->assertJsonCount(0, 'locations');
        $this->getJson('/api/locations?shop='.$hidden->slug)->assertOk()->assertJsonCount(0, 'locations');
    }

    public function test_shop_owner_can_publish_or_clear_a_pin_without_overwriting_shop_hours(): void
    {
        $this->signIn('manager');
        $profile = [
            'name' => 'Forge & Fade',
            'address' => '12 Example Street, Cape Town, 8001',
            'directions_url' => 'https://www.google.com/maps/place/Forge+%26+Fade/',
        ];
        $this->patchJson('/api/admin/shop', $profile)->assertForbidden();

        $this->signIn('owner');
        $other = Shop::create(['name' => 'Other', 'slug' => 'other-location']);
        $this->patchJson('/api/admin/shop', $profile, ['X-Shop-Id' => $other->id])->assertForbidden();
        $this->patchJson('/api/admin/shop', array_replace($profile, ['directions_url' => 'https://example.com/maps/pin']))
            ->assertUnprocessable()->assertJsonValidationErrors('directions_url');
        $this->patchJson('/api/admin/shop', $profile)->assertOk()
            ->assertJsonPath('directions_url', $profile['directions_url']);
        $this->getJson('/api/admin/shop')->assertOk()->assertJsonPath('directions_url', $profile['directions_url']);
        $this->getJson('/api/bookings/catalog')->assertOk()->assertJsonPath('shop.directions_url', $profile['directions_url']);
        $this->putJson('/api/admin/management/business-hours', [
            'version' => 0, 'hours' => [1 => ['09:00', '16:00']] + array_fill(2, 6, null),
            'breaks' => [], 'closed_dates' => [],
        ])->assertOk();
        $this->getJson('/api/locations')->assertOk()
            ->assertJsonPath('locations.0.address', $profile['address'])
            ->assertJsonPath('locations.0.directions_url', $profile['directions_url'])
            ->assertJsonPath('locations.0.hours.1', ['09:00', '16:00']);
        $this->patchJson('/api/admin/shop', array_replace($profile, ['directions_url' => null]))
            ->assertOk()->assertJsonPath('directions_url', null);
        $this->getJson('/api/locations')->assertOk()
            ->assertJsonPath('locations.0.directions_url', null)
            ->assertJsonPath('locations.0.hours.1', ['09:00', '16:00']);
    }
}
