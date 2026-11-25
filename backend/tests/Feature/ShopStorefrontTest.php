<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShopStorefrontTest extends TestCase
{
    use RefreshDatabase;

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('photo.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lxoAAAAASUVORK5CYII=',
        ));
    }

    public function test_only_the_owner_can_publish_branding_and_barber_photos_in_their_shop(): void
    {
        Storage::fake('local');
        $other = Shop::create(['name' => 'Other Shop', 'slug' => 'other-storefront', 'timezone' => 'Africa/Johannesburg']);
        $manager = User::factory()->create();
        $this->grantShopRole($manager, 'manager');
        Sanctum::actingAs($manager, ['admin']);
        $this->postJson('/api/admin/management/media', ['version' => 0, 'target' => 'logo', 'photo' => $this->photo()])
            ->assertForbidden();

        $owner = User::factory()->create();
        $this->grantShopRole($owner);
        Sanctum::actingAs($owner, ['admin']);
        $this->putJson('/api/admin/management/branding', ['version' => 0, 'accent' => '#376C62'])
            ->assertOk()->assertJsonPath('branding.accent', '#376C62');
        $logo = $this->post('/api/admin/management/media', ['version' => 1, 'target' => 'logo', 'photo' => $this->photo()])
            ->assertOk()->json('branding.logo');
        $cover = $this->post('/api/admin/management/media', ['version' => 2, 'target' => 'cover', 'photo' => $this->photo()])
            ->assertOk()->json('branding.cover');
        $photo = $this->post('/api/admin/management/media', ['version' => 3, 'target' => 'portfolio', 'barber_id' => 1,
            'photo' => $this->photo(), 'caption' => 'Textured crop'])
            ->assertOk()->json('barbers.0.portfolio.0.image');
        $this->post('/api/admin/management/media', ['version' => 4, 'target' => 'cover',
            'photo' => UploadedFile::fake()->createWithContent('photo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>')],
            ['Accept' => 'application/json'])
            ->assertUnprocessable();
        $this->postJson('/api/admin/management/media', ['version' => 3, 'target' => 'logo', 'remove' => $logo])->assertConflict();
        $this->putJson('/api/admin/management/barbers/1', ['version' => 4, 'name' => 'Barber updated', 'active' => true, 'bio' => 'Short cuts and clean lines.'])
            ->assertOk()->assertJsonPath('barbers.0.portfolio.0.image', $photo);
        $this->getJson('/api/bookings/catalog')->assertOk()
            ->assertJsonPath('branding.logo', $logo)->assertJsonPath('branding.cover', $cover)
            ->assertJsonPath('barbers.0.bio', 'Short cuts and clean lines.')
            ->assertJsonPath('barbers.0.portfolio.0.caption', 'Textured crop');
        $this->get('/api/shop-media/forge-and-fade/'.$photo)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get('/api/shop-media/'.$other->slug.'/'.$photo)->assertNotFound();

        $this->putJson('/api/admin/management/barbers/1', ['version' => 5, 'name' => 'Barber updated', 'active' => false])
            ->assertOk();
        $this->get('/api/shop-media/forge-and-fade/'.$photo)->assertNotFound();
        $this->get('/api/admin/management/media/'.$photo)->assertOk();
        $this->postJson('/api/admin/management/media', ['version' => 6, 'target' => 'cover', 'remove' => $cover])->assertOk();
        $this->get('/api/shop-media/forge-and-fade/'.$cover)->assertNotFound();
    }

    public function test_next_available_uses_the_same_schedule_and_capacity_as_the_date_picker(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-07 17:50', 'Africa/Johannesburg'));
        try {
            $shop = Shop::findOrFail(1);
            $shop->update(['settings' => [...$shop->settings, 'closed_dates' => ['2026-11-09']]]);
            $this->postJson('/api/bookings', [
                'service_id' => 1, 'barber_id' => 1, 'booking_date' => '2026-11-10', 'booking_time' => '08:00',
                'customer_name' => 'Availability Test', 'email' => 'slots@example.test', 'phone' => '0710000000',
            ])->assertCreated();
            $this->getJson('/api/bookings/next-available?service_id=1&barber_id=1')->assertOk()
                ->assertJsonCount(4, 'slots')
                ->assertJsonPath('slots.0', ['booking_date' => '2026-11-10', 'booking_time' => '08:50']);
            $this->getJson('/api/bookings/availability?service_id=1&barber_id=1&booking_date=2026-11-10')->assertOk()
                ->assertJsonPath('available_times.0', '08:50');
            $this->getJson('/api/bookings/next-available?service_id=1&barber_id=any')->assertOk()
                ->assertJsonPath('slots.0', ['booking_date' => '2026-11-10', 'booking_time' => '08:00']);
        } finally {
            Carbon::setTestNow();
        }
    }
}
