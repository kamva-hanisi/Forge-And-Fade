<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicBusinessTest extends TestCase
{
    use RefreshDatabase;

    private function choose(User $user, int $shop)
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('public-business-test', ['admin'])->plainTextToken)
            ->putJson('/api/platform/shops/'.$shop.'/public-default');
    }

    private function secondBusiness(): Shop
    {
        $settings = Shop::findOrFail(1)->settings;
        $serviceId = array_key_first($settings['services']);
        $settings['services'][$serviceId]['name'] = 'Kamva signature cut';

        return Shop::create(['name' => 'Kamva', 'slug' => 'kamva-2026', 'phone' => '0712345678', 'settings' => $settings]);
    }

    public function test_website_owner_switches_the_public_catalog_and_contact_routing(): void
    {
        Mail::fake();
        $shop = $this->secondBusiness();
        $owner = User::factory()->create(['is_admin' => true, 'website_role' => 'owner']);
        $this->getJson('/api/bookings/catalog')->assertOk()->assertJsonPath('shop.slug', 'forge-and-fade');
        $this->choose($owner, $shop->id)->assertOk()->assertJsonPath('user.shop.id', $shop->id)
            ->assertJsonPath('user.public_shop_id', $shop->id);
        $this->getJson('/api/admin/session')->assertOk()->assertJsonPath('user.public_shop_id', $shop->id);
        $this->assertDatabaseHas('audit_events', ['action' => 'website.public_business_changed', 'user_id' => $owner->id, 'shop_id' => $shop->id]);

        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->getJson('/api/bookings/catalog')->assertOk()->assertJsonPath('shop.name', 'Kamva')
            ->assertJsonPath('shop.phone', '0712345678')->assertJsonPath('services.0.name', 'Kamva signature cut');
        $this->getJson('/api/bookings/catalog?shop=forge-and-fade')->assertOk()->assertJsonPath('shop.slug', 'forge-and-fade');
        $this->getJson('/api/bookings/catalog?shop=unknown')->assertNotFound();

        $message = ['name' => 'Customer', 'email' => 'customer@example.test', 'message' => 'Default enquiry'];
        $this->postJson('/api/contact', $message)->assertCreated();
        $this->postJson('/api/contact', [...$message, 'shop' => 'forge-and-fade', 'message' => 'Explicit enquiry'])->assertCreated();
        $this->assertDatabaseHas('contact_messages', ['shop_id' => $shop->id, 'message' => 'Default enquiry']);
        $this->assertDatabaseHas('contact_messages', ['shop_id' => 1, 'message' => 'Explicit enquiry']);
        Mail::assertNothingSent();

        $this->choose($owner, 1)->assertOk()->assertJsonPath('user.public_shop_id', 1);
        $this->getJson('/api/bookings/catalog')->assertOk()->assertJsonPath('shop.slug', 'forge-and-fade');
    }

    public function test_only_the_website_owner_can_change_the_public_default(): void
    {
        $shop = $this->secondBusiness();
        $this->putJson('/api/platform/shops/'.$shop->id.'/public-default')->assertUnauthorized();

        $businessOwner = User::factory()->create();
        $this->grantShopRole($businessOwner, 'owner', $shop->id);
        $this->choose($businessOwner, $shop->id)->assertForbidden();
        $super = User::factory()->create(['is_admin' => true, 'website_role' => 'super_admin']);
        $this->choose($super, $shop->id)->assertForbidden();
        $suspendedOwner = User::factory()->create(['is_admin' => true, 'website_role' => 'owner', 'website_suspended' => true]);
        $this->choose($suspendedOwner, $shop->id)->assertForbidden();
        $this->getJson('/api/bookings/catalog')->assertOk()->assertJsonPath('shop.slug', 'forge-and-fade');
        $this->assertDatabaseMissing('audit_events', ['action' => 'website.public_business_changed']);
    }

    public function test_deleting_the_current_default_uses_the_remaining_business(): void
    {
        $shop = $this->secondBusiness();
        $owner = User::factory()->create(['is_admin' => true, 'website_role' => 'owner']);
        $this->choose($owner, 1)->assertOk()->assertJsonPath('user.public_shop_id', 1);
        $this->deleteJson('/api/platform/shops/1', ['confirm_slug' => 'forge-and-fade'])
            ->assertOk()->assertJsonPath('user.public_shop_id', $shop->id);
        $this->getJson('/api/bookings/catalog')->assertOk()->assertJsonPath('shop.slug', $shop->slug);
        $this->getJson('/api/bookings/catalog?shop=forge-and-fade')->assertNotFound();
    }
}
