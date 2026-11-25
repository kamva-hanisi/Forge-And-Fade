<?php

namespace Tests\Feature;

use App\Mail\BookingEmail;
use App\Models\Booking;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ShopLifecycleTest extends TestCase
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

    private function authenticate(bool $platform): User
    {
        $user = User::factory()->create(['is_admin' => true, 'is_platform_admin' => $platform]);
        $this->grantShopRole($user, 'owner');
        app('auth')->forgetGuards();
        $this->withToken($user->createToken('lifecycle-test', ['admin'])->plainTextToken);

        return $user;
    }

    private function statusPayload(string $status = 'suspended', int $revision = 0): array
    {
        return ['status' => $status, 'revision' => $revision, 'confirm_slug' => 'forge-and-fade'];
    }

    private function booking(): array
    {
        return ['service_id' => 2, 'barber_id' => 1, 'booking_date' => '2026-11-03', 'booking_time' => '10:00',
            'customer_name' => 'Lifecycle QA', 'email' => 'lifecycle@example.com', 'phone' => '0712345678'];
    }

    public function test_only_platform_can_change_status_with_explicit_confirmation_and_current_revision(): void
    {
        $this->putJson('/api/platform/shops/1/status', $this->statusPayload())->assertUnauthorized();
        $this->authenticate(false);
        $this->putJson('/api/platform/shops/1/status', $this->statusPayload())->assertForbidden();
        $this->authenticate(true);
        $this->putJson('/api/platform/shops/1/status', [...$this->statusPayload(), 'confirm_slug' => 'wrong'])->assertUnprocessable();
        $this->putJson('/api/platform/shops/1/status', [...$this->statusPayload(), 'status' => 'deleted'])->assertUnprocessable();
        $this->putJson('/api/platform/shops/1/status', $this->statusPayload())->assertOk()->assertJsonPath('shop.lifecycle_revision', 1);
        $this->putJson('/api/platform/shops/1/status', $this->statusPayload('active'))->assertConflict();
        $this->putJson('/api/platform/shops/1/status', $this->statusPayload('active', 1))->assertOk()->assertJsonPath('shop.status', 'active');
        $this->assertDatabaseHas('audit_events', ['action' => 'shop.suspended', 'subject_id' => 1]);
        $this->assertDatabaseHas('audit_events', ['action' => 'shop.reactivated', 'subject_id' => 1]);
    }

    public function test_suspension_blocks_new_requests_but_keeps_customer_lookup_and_cancellation(): void
    {
        $booking = $this->postJson('/api/bookings', $this->booking())->assertCreated()->json('booking');
        $this->authenticate(true);
        $this->putJson('/api/platform/shops/1/status', $this->statusPayload())->assertOk();
        $this->getJson('/api/bookings/catalog')->assertOk()->assertJsonPath('booking_status', 'suspended')->assertJsonCount(0, 'services')->assertJsonCount(0, 'barbers');
        $this->getJson('/api/bookings/availability?service_id=2&barber_id=1&booking_date=2026-11-03')->assertNotFound();
        $this->postJson('/api/bookings', $this->booking())->assertNotFound();
        $this->postJson('/api/contact', ['name' => 'Customer', 'email' => 'guest@example.com', 'message' => 'Test message'])->assertNotFound();
        $credentials = ['reference' => $booking['reference'], 'email' => 'lifecycle@example.com'];
        $this->postJson('/api/bookings/lookup', $credentials)->assertOk()->assertJsonPath('booking.status', 'confirmed');
        $this->postJson('/api/bookings/cancel', [...$credentials, 'revision' => 0])->assertOk()->assertJsonPath('booking.status', 'cancelled');
        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_shop_staff_keep_read_and_security_access_but_cannot_mutate_operations(): void
    {
        $booking = $this->postJson('/api/bookings', $this->booking())->assertCreated()->json('booking');
        $this->authenticate(true);
        $this->putJson('/api/platform/shops/1/status', $this->statusPayload())->assertOk();
        $this->authenticate(false);
        $this->getJson('/api/admin/dashboard')->assertOk()->assertJsonPath('shop_status', 'suspended');
        $this->getJson('/api/admin/bookings')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/admin/profile')->assertOk();
        $this->postJson('/api/admin/bookings', $this->booking())->assertForbidden();
        $this->putJson('/api/admin/management/business-hours', ['version' => 0])->assertForbidden();
        $this->patchJson('/api/admin/bookings/'.$booking['id'], ['action' => 'reschedule', 'revision' => 0])->assertForbidden();
        $this->patchJson('/api/admin/profile', ['name' => 'Updated owner'])->assertOk();
        $this->patchJson('/api/admin/bookings/'.$booking['id'], ['action' => 'cancel', 'revision' => 0])->assertOk();
        $this->postJson('/api/admin/logout')->assertOk();
    }

    public function test_reactivation_preserves_records_settings_and_other_shops(): void
    {
        $this->postJson('/api/bookings', $this->booking())->assertCreated();
        $before = Booking::firstOrFail()->toJson();
        $settings = Shop::findOrFail(1)->settings;
        $other = Shop::create(['name' => 'Other', 'slug' => 'lifecycle-other']);
        $this->authenticate(true);
        $this->putJson('/api/platform/shops/1/status', $this->statusPayload())->assertOk();
        $this->assertSame($before, Booking::firstOrFail()->toJson());
        $this->putJson('/api/platform/shops/1/status', $this->statusPayload('active', 1))->assertOk();
        $this->assertSame($settings, Shop::findOrFail(1)->settings);
        $this->assertSame('active', $other->fresh()->status);
        $this->getJson('/api/bookings/catalog')->assertOk()->assertJsonCount(6, 'services');
        $this->postJson('/api/bookings', [...$this->booking(), 'booking_time' => '11:00'])->assertCreated();
    }

    public function test_paused_emails_are_not_sent_and_resume_without_using_retry_attempts(): void
    {
        config(['booking_mail.enabled' => true, 'mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.test',
            'mail.mailers.smtp.username' => 'test', 'mail.mailers.smtp.password' => 'test', 'mail.from.address' => 'shop@example.test']);
        Mail::fake();
        $this->postJson('/api/bookings', $this->booking())->assertCreated();
        $this->authenticate(true);
        $this->putJson('/api/platform/shops/1/status', $this->statusPayload())->assertOk();
        $this->artisan('bookings:notify')->assertSuccessful();
        Mail::assertNothingSent();
        $this->assertDatabaseHas('booking_emails', ['kind' => 'confirmation', 'status' => 'pending', 'attempts' => 0]);
        $this->putJson('/api/platform/shops/1/status', $this->statusPayload('active', 1))->assertOk();
        $this->artisan('bookings:notify')->assertSuccessful();
        Mail::assertSent(BookingEmail::class, 1);
    }
}
