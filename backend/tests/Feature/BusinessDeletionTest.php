<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class BusinessDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function withAccount(User $user, string $ability = 'admin'): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('delete-business-test', [$ability])->plainTextToken);
    }

    private function owner(): User
    {
        return User::factory()->create(['is_admin' => true, 'website_role' => 'owner']);
    }

    public function test_deletion_requires_an_active_website_owner_and_exact_identifier(): void
    {
        $url = '/api/platform/shops/1';
        $match = ['confirm_slug' => 'forge-and-fade'];
        $this->deleteJson($url, $match)->assertUnauthorized();
        $legacy = User::factory()->create(['is_admin' => true, 'is_platform_admin' => true]);
        $this->withAccount($legacy)->deleteJson($url, $match)->assertForbidden();
        $businessOwner = User::factory()->create();
        $this->grantShopRole($businessOwner);
        $this->withAccount($businessOwner)->deleteJson($url, $match)->assertForbidden();
        $superAdmin = User::factory()->create(['is_admin' => true, 'website_role' => 'super_admin']);
        $this->withAccount($superAdmin)->deleteJson($url, $match)->assertForbidden();
        $admin = User::factory()->create(['is_admin' => true, 'website_role' => 'admin']);
        $this->withAccount($admin)->deleteJson($url, $match)->assertForbidden();
        $owner = $this->owner();
        $this->withAccount($owner, 'profile')->deleteJson($url, $match)->assertForbidden();
        $this->withAccount($owner)->deleteJson($url, ['confirm_slug' => 'Forge-and-fade'])->assertUnprocessable();
        $this->deleteJson($url, ['confirm_slug' => 'forge-and-fade '])->assertUnprocessable();
        $this->deleteJson($url)->assertUnprocessable()->assertJsonValidationErrors('confirm_slug');
        $this->assertDatabaseHas('shops', ['id' => 1, 'slug' => 'forge-and-fade']);
        $this->assertDatabaseMissing('audit_events', ['action' => 'shop.deleted']);
    }

    public function test_deletion_removes_only_the_selected_business_and_retires_its_booking_link(): void
    {
        Storage::fake('local');
        Mail::fake();
        config(['booking_mail.enabled' => true]);
        $owner = $this->owner();
        $year = now('Africa/Johannesburg')->year;
        $identifier = "de-$year-ts";
        $business = Shop::create(['name' => 'Delete Cuts', 'slug' => $identifier]);
        $this->grantShopRole($owner, 'owner', $business->id);
        $staff = User::factory()->create(['is_admin' => true, 'website_role' => 'admin']);
        $this->grantShopRole($staff, 'manager', 1);
        $this->grantShopRole($staff, 'manager', $business->id);
        foreach ([1, $business->id] as $shop) {
            DB::table('website_shop_access')->insert(['user_id' => $staff->id, 'shop_id' => $shop,
                'permissions' => '["bookings.read"]']);
        }
        $kept = $this->booking(1);
        $removed = $this->booking($business->id);
        foreach ([$kept, $removed] as $booking) {
            DB::table('booking_payments')->insert(['shop_id' => $booking->shop_id, 'booking_id' => $booking->id,
                'amount' => 100, 'currency' => 'ZAR', 'method' => 'cash', 'received_on' => now()->toDateString(),
                'recorded_by' => $owner->id]);
            DB::table('shop_booking_day_locks')->insert(['shop_id' => $booking->shop_id, 'barber_id' => 1,
                'booking_date' => '2030-01-01']);
        }
        $message = DB::table('contact_messages')->insertGetId(['shop_id' => $business->id, 'name' => 'Fixture',
            'email' => 'guest@example.test', 'message' => 'Pending enquiry']);
        $otherMessage = DB::table('contact_messages')->insertGetId(['shop_id' => 1, 'name' => 'Other fixture',
            'email' => 'other-guest@example.test', 'message' => 'Keep this enquiry']);
        DB::table('contact_replies')->insert(['contact_message_id' => $message, 'request_id' => (string) Str::uuid(), 'body' => 'Pending reply']);
        DB::table('admin_invitations')->insert(['shop_id' => $business->id, 'email' => 'staff@example.test',
            'token_hash' => hash('sha256', 'staff-test'), 'expires_at' => now()->addDay()]);
        $invitation = DB::table('website_admin_invitations')->insertGetId(['email' => 'super@example.test',
            'token_hash' => hash('sha256', 'website-test'), 'invited_by' => $owner->id,
            'assignments' => json_encode([1 => ['bookings.read'], $business->id => ['messages.manage']]),
            'expires_at' => now()->addDay(), 'created_at' => now()]);
        Storage::disk('local')->put('shop-media/'.$business->id.'/remove.jpg', 'fixture');
        Storage::disk('local')->put('shop-media/1/keep.jpg', 'fixture');

        $this->withAccount($owner)->deleteJson('/api/platform/shops/'.$business->id, ['confirm_slug' => $identifier])
            ->assertOk()->assertJsonPath('user.website_role', 'owner')->assertJsonPath('user.shop.id', 1)
            ->assertJsonCount(1, 'user.shops');
        $this->assertDatabaseMissing('shops', ['id' => $business->id]);
        $this->assertDatabaseHas('retired_shop_slugs', ['slug' => $identifier]);
        $this->assertDatabaseHas('bookings', ['id' => $kept->id]);
        $this->assertDatabaseHas('booking_payments', ['booking_id' => $kept->id]);
        $this->assertDatabaseHas('booking_emails', ['booking_id' => $kept->id]);
        $this->assertDatabaseHas('contact_messages', ['id' => $otherMessage]);
        $this->assertDatabaseHas('shop_memberships', ['user_id' => $staff->id, 'shop_id' => 1]);
        $this->assertDatabaseHas('website_shop_access', ['user_id' => $staff->id, 'shop_id' => 1]);
        $this->assertDatabaseHas('users', ['id' => $staff->id, 'website_access_version' => 1]);
        $this->assertDatabaseHas('users', ['id' => $owner->id, 'website_role' => 'owner']);
        foreach (['bookings', 'booking_payments', 'contact_messages', 'admin_invitations', 'shop_booking_day_locks',
            'shop_memberships', 'website_shop_access'] as $table) {
            $this->assertDatabaseMissing($table, ['shop_id' => $business->id]);
        }
        $this->assertDatabaseMissing('contact_replies', ['contact_message_id' => $message]);
        $this->assertDatabaseMissing('booking_emails', ['booking_id' => $removed->id]);
        $this->assertSame([1 => ['bookings.read']], json_decode(
            DB::table('website_admin_invitations')->where('id', $invitation)->value('assignments'), true));
        Storage::disk('local')->assertMissing('shop-media/'.$business->id.'/remove.jpg');
        Storage::disk('local')->assertExists('shop-media/1/keep.jpg');
        Mail::assertNothingSent();

        $this->getJson('/api/bookings/catalog?shop='.$identifier)->assertNotFound();
        $this->deleteJson('/api/platform/shops/'.$business->id, ['confirm_slug' => $identifier])->assertNotFound();
        $this->getJson('/api/admin/roles')->assertOk()
            ->assertJsonPath('activity.data.0.action', 'shop.deleted')
            ->assertJsonPath('activity.data.0.details.slug', $identifier);
        $this->postJson('/api/admin/register-shop', [
            'shop_name' => 'Delete Cuts', 'name' => 'Another owner', 'email' => 'new-business@example.test',
            'password' => 'Replacement-12345!', 'password_confirmation' => 'Replacement-12345!',
        ])->assertCreated()->assertJsonPath('user.shop.slug', $identifier.'-2');
    }

    public function test_owner_keeps_platform_access_after_deleting_the_last_business(): void
    {
        $this->withAccount($this->owner())->deleteJson('/api/platform/shops/1', ['confirm_slug' => 'forge-and-fade'])
            ->assertOk()->assertJsonPath('user.shop', null)->assertJsonPath('user.platform_access', true);
        $this->getJson('/api/admin/session')->assertOk()->assertJsonPath('user.shop', null);
        $this->getJson('/api/platform/overview')->assertOk()->assertJsonPath('summary.shops', 0);
        $this->getJson('/api/bookings/catalog')->assertNotFound();
    }

    private function booking(int $shop): Booking
    {
        return Booking::create(['shop_id' => $shop, 'service_id' => 2, 'service_name' => 'Cut',
            'barber_id' => 1, 'barber_name' => 'Test barber', 'duration' => 30, 'booking_date' => '2030-01-01',
            'booking_time' => '10:00', 'customer_name' => 'Fixture', 'email' => 'customer@example.test',
            'phone' => '0712345678', 'status' => 'confirmed']);
    }
}
