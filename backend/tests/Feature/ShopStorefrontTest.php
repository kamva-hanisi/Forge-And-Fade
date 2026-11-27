<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

    public function test_generated_branding_publishes_as_a_pair_for_the_selected_business(): void
    {
        Storage::fake('local');
        $other = Shop::create(['name' => 'Kamva’s', 'slug' => 'kamvas', 'timezone' => 'Africa/Johannesburg']);
        $owner = User::factory()->create();
        $this->grantShopRole($owner);
        Sanctum::actingAs($owner, ['admin']);
        $name = Shop::findOrFail(1)->name;
        $url = '/api/admin/management/branding/images';
        $files = fn () => ['logo' => $this->photo(), 'cover' => $this->photo()];

        $original = $this->post($url, ['version' => 0, 'business_name' => $name, ...$files()])
            ->assertOk()->assertJsonPath('version', 1)->json('branding');
        $this->post($url, ['version' => 0, 'business_name' => $other->name, ...$files()], ['X-Shop-Id' => $other->id])
            ->assertForbidden();
        $this->grantShopRole($owner, 'owner', $other->id);
        $otherImages = $this->post($url, ['version' => 0, 'business_name' => $other->name, ...$files()], ['X-Shop-Id' => $other->id])
            ->assertOk()->assertJsonPath('business_name', $other->name)->json('branding');

        $this->post($url, ['version' => 0, 'business_name' => $name, ...$files()])->assertConflict();
        $this->post($url, ['version' => 1, 'business_name' => $other->name, ...$files()])->assertConflict();
        $this->post($url, ['version' => 1, 'business_name' => $name, 'logo' => $this->photo()], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('cover');
        $this->getJson('/api/bookings/catalog?shop=forge-and-fade')->assertOk()
            ->assertJsonPath('branding.logo', $original['logo'])->assertJsonPath('branding.cover', $original['cover']);
        $this->assertCount(2, Storage::disk('local')->files('shop-media/1'));

        $replacement = $this->post($url, ['version' => 1, 'business_name' => $name, ...$files()])
            ->assertOk()->assertJsonPath('version', 2)->json('branding');
        $this->getJson('/api/bookings/catalog?shop=forge-and-fade')->assertOk()
            ->assertJsonPath('branding.logo', $replacement['logo'])->assertJsonPath('branding.cover', $replacement['cover']);
        $this->getJson('/api/bookings/catalog?shop=kamvas')->assertOk()
            ->assertJsonPath('branding.logo', $otherImages['logo'])->assertJsonPath('branding.cover', $otherImages['cover']);
        foreach (['logo', 'cover'] as $target) {
            $this->get('/api/shop-media/kamvas/'.$otherImages[$target])->assertOk()->assertHeader('Content-Type', 'image/png');
            $this->get('/api/shop-media/kamvas/'.$replacement[$target])->assertNotFound();
            $this->get('/api/shop-media/forge-and-fade/'.$original[$target])->assertNotFound();
        }
        $this->assertDatabaseHas('audit_events', ['user_id' => $owner->id, 'shop_id' => $other->id, 'action' => 'shop.branding_saved']);
    }

    public function test_service_description_is_published_and_preserved_by_older_catalog_editors(): void
    {
        $owner = User::factory()->create();
        $this->grantShopRole($owner);
        Sanctum::actingAs($owner, ['admin']);
        $edit = ['name' => 'Classic Haircut', 'duration' => 45, 'price' => 240, 'active' => true];
        $this->putJson('/api/admin/management/services/1', $edit + ['version' => 0, 'description' => 'A trim finished with styling.'])
            ->assertOk();
        $this->putJson('/api/admin/management/services/1', $edit + ['version' => 1])->assertOk();
        $this->getJson('/api/bookings/catalog')->assertOk()
            ->assertJsonPath('services.0.description', 'A trim finished with styling.');
    }

    public function test_branding_can_be_delegated_without_catalog_or_schedule_access(): void
    {
        Storage::fake('local');
        $other = Shop::create(['name' => 'Other Business', 'slug' => 'other-branding', 'timezone' => 'Africa/Johannesburg']);
        $owner = User::factory()->create(['is_admin' => true, 'website_role' => 'owner']);
        $super = User::factory()->create();
        Sanctum::actingAs($owner, ['admin']);
        $management = '/api/admin/management';
        $options = $this->getJson('/api/admin/roles')->assertOk()->json('permission_options');
        $this->assertContains('shop.branding.write', $options['super_admin']);
        $this->assertNotContains('shop.branding.write', $options['admin']);
        $this->putJson('/api/admin/roles/accounts/'.$super->id, [
            'version' => 0, 'role' => 'super_admin', 'suspended' => false,
            'assignments' => [
                ['shop_id' => 1, 'permissions' => ['shop.branding.write']],
                ['shop_id' => $other->id, 'permissions' => ['shop.manage']],
            ],
        ])->assertOk();
        $portfolio = $this->post($management.'/media', ['version' => 0, 'target' => 'portfolio', 'barber_id' => 1, 'photo' => $this->photo()])
            ->assertOk()->json('barbers.0.portfolio.0.image');
        Sanctum::actingAs($super->fresh(), ['admin']);

        $this->getJson($management.'/branding', ['X-Shop-Id' => 1])->assertOk()
            ->assertJsonPath('version', 1)->assertJsonMissingPath('services')->assertJsonMissingPath('schedules');
        $this->getJson($management)->assertForbidden();
        $this->getJson($management.'/business-hours')->assertForbidden();
        $this->postJson($management.'/services', ['version' => 1])->assertForbidden();
        $this->putJson($management.'/barbers/1/schedule', ['version' => 1])->assertForbidden();
        $this->putJson($management.'/business-hours', ['version' => 1])->assertForbidden();
        $this->post($management.'/media', ['version' => 1, 'target' => 'portfolio', 'barber_id' => 1, 'photo' => $this->photo()])
            ->assertForbidden();
        $this->get($management.'/media/'.$portfolio)->assertNotFound();
        $this->getJson($management.'/branding', ['X-Shop-Id' => $other->id])->assertForbidden();
        $this->getJson($management, ['X-Shop-Id' => $other->id])->assertOk();
        $this->post($management.'/media', ['version' => 0, 'target' => 'cover', 'photo' => $this->photo()], ['X-Shop-Id' => $other->id])
            ->assertForbidden();

        $images = $this->post($management.'/branding/images', [
            'version' => 1, 'business_name' => Shop::findOrFail(1)->name,
            'logo' => $this->photo(), 'cover' => $this->photo(),
        ])->assertOk()->assertJsonPath('version', 2)->assertJsonMissingPath('barbers')->json('branding');
        $this->get($management.'/media/'.$images['logo'])->assertOk();
        $this->post($management.'/media', ['version' => 2, 'target' => 'cover', 'photo' => $this->photo()])
            ->assertOk()->assertJsonPath('version', 3);
        $this->putJson($management.'/branding', ['version' => 3, 'accent' => '#376C62'])
            ->assertOk()->assertJsonPath('branding.accent', '#376C62');
        $this->postJson($management.'/media', ['version' => 4, 'target' => 'logo', 'remove' => $images['logo']])
            ->assertOk()->assertJsonMissingPath('branding.logo');
    }

    public function test_migration_preserves_branding_access_for_existing_super_admins_and_pending_invitations(): void
    {
        $super = User::factory()->create(['is_admin' => true, 'website_role' => 'super_admin']);
        $owner = User::factory()->create(['is_admin' => true, 'website_role' => 'owner']);
        $other = Shop::create(['name' => 'Other Business', 'slug' => 'migration-branding', 'timezone' => 'Africa/Johannesburg']);
        DB::table('website_shop_access')->insert(['user_id' => $super->id, 'shop_id' => 1, 'permissions' => '["shop.manage"]']);
        $invitation = DB::table('website_admin_invitations')->insertGetId([
            'email' => 'branding-invite@example.test', 'token_hash' => hash('sha256', 'fixture-branding'), 'invited_by' => $owner->id,
            'assignments' => json_encode([1 => ['shop.manage'], $other->id => ['bookings.read']]),
            'expires_at' => now()->addDay(), 'created_at' => now(),
        ]);
        $migration = require database_path('migrations/2026_11_27_000005_separate_branding_permission.php');
        $migration->up();
        $migration->up();

        $this->assertSame(1, $super->fresh()->website_access_version);
        $this->assertSame([1 => ['shop.manage', 'shop.branding.write'], $other->id => ['bookings.read']], json_decode(
            DB::table('website_admin_invitations')->where('id', $invitation)->value('assignments'), true));
        Sanctum::actingAs($super->fresh(), ['admin']);
        $this->getJson('/api/admin/management/branding')->assertOk();
    }
}
