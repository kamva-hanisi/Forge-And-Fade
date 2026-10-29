<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-02 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        $user = User::factory()->create(['password' => 'Admin-Testing-Password-123']);
        $user->forceFill(['is_admin' => true])->save();

        return $user;
    }

    public function test_admin_can_be_provisioned_with_a_hidden_password_prompt(): void
    {
        $this->artisan('admin:create', ['email' => 'owner@example.com', '--name' => 'Shop Owner'])
            ->expectsQuestion('Choose a password (at least 12 characters)', 'Owner-Password-12345')
            ->assertExitCode(0);
        $owner = User::where('email', 'owner@example.com')->firstOrFail();
        $this->assertTrue($owner->is_admin);
        $this->assertTrue(Hash::check('Owner-Password-12345', $owner->password));
    }

    private function booking(string $time = '10:00'): Booking
    {
        $response = $this->postJson('/api/bookings', [
            'service_id' => 2, 'barber_id' => 1, 'booking_date' => '2026-11-03', 'booking_time' => $time,
            'customer_name' => 'Admin QA', 'email' => 'admin-qa@example.com', 'phone' => '0712345678',
        ])->assertCreated();

        return Booking::findOrFail($response->json('booking.id'));
    }

    public function test_login_issues_expiring_token_and_logout_revokes_it(): void
    {
        $user = $this->admin();
        $token = $this->postJson('/api/admin/login', ['email' => $user->email, 'password' => 'Admin-Testing-Password-123'])
            ->assertOk()->assertJsonMissingPath('user.password')->json('token');
        $this->assertNotNull($user->tokens()->firstOrFail()->expires_at);
        $this->withToken($token)->getJson('/api/admin/bookings')->assertOk();
        $this->withToken($token)->postJson('/api/admin/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_anonymous_and_non_admin_users_cannot_access_bookings(): void
    {
        $booking = $this->booking();
        $this->getJson('/api/admin/bookings')->assertUnauthorized();
        $this->patchJson('/api/admin/bookings/'.$booking->id, ['action' => 'cancel', 'revision' => 0])->assertUnauthorized();
        $ordinary = User::factory()->create();
        Sanctum::actingAs($ordinary, ['admin']);
        $this->getJson('/api/admin/bookings')->assertForbidden();
        $this->patchJson('/api/admin/bookings/'.$booking->id, ['action' => 'cancel', 'revision' => 0])->assertForbidden();
        Sanctum::actingAs($this->admin(), []);
        $this->getJson('/api/admin/bookings')->assertForbidden();
    }

    public function test_invalid_credentials_and_ordinary_accounts_are_rejected(): void
    {
        $user = User::factory()->create(['password' => 'Admin-Testing-Password-123']);
        foreach ([$user->email, 'unknown@example.com'] as $email) {
            $this->postJson('/api/admin/login', ['email' => $email, 'password' => 'Admin-Testing-Password-123'])->assertUnprocessable();
        }
        $admin = $this->admin();
        $this->postJson('/api/admin/login', ['email' => $admin->email, 'password' => 'wrong'])->assertUnprocessable();
    }

    public function test_login_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/admin/login', ['email' => 'unknown@example.com', 'password' => 'wrong'])->assertUnprocessable();
        }
        $this->postJson('/api/admin/login', ['email' => 'unknown@example.com', 'password' => 'wrong'])->assertTooManyRequests();
    }

    public function test_admin_can_filter_list_and_cancel_with_slot_reuse(): void
    {
        $booking = $this->booking();
        Sanctum::actingAs($this->admin(), ['admin']);
        $this->getJson('/api/admin/bookings?date_from=2026-11-03&status=confirmed&barber_id=1&search=Admin')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/admin/bookings?date_to=2026-11-02')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/admin/bookings?date_from=2026-11-04&date_to=2026-11-03')->assertUnprocessable();
        $this->patchJson('/api/admin/bookings/'.$booking->id, ['action' => 'cancel', 'revision' => 0])->assertOk()
            ->assertJsonPath('booking.status', 'cancelled')->assertJsonPath('booking.revision', 1);
        $this->booking();
        $this->assertDatabaseCount('bookings', 2);
        $this->patchJson('/api/admin/bookings/'.$booking->id, ['action' => 'cancel', 'revision' => 0])->assertConflict();
    }

    public function test_reschedule_rejects_overlap_and_breaks_and_preserves_duration(): void
    {
        $booking = $this->booking();
        $this->booking('11:00');
        Sanctum::actingAs($this->admin(), ['admin']);
        $payload = ['action' => 'reschedule', 'revision' => 0, 'barber_id' => 1, 'booking_date' => '2026-11-03', 'booking_time' => '10:30'];
        $this->patchJson('/api/admin/bookings/'.$booking->id, $payload)->assertConflict();
        $this->patchJson('/api/admin/bookings/'.$booking->id, [...$payload, 'booking_time' => '13:00'])->assertUnprocessable();
        $this->patchJson('/api/admin/bookings/'.$booking->id, [...$payload, 'barber_id' => 999])->assertUnprocessable();
        $this->patchJson('/api/admin/bookings/'.$booking->id, [...$payload, 'booking_time' => '10:00'])->assertOk();
        $this->patchJson('/api/admin/bookings/'.$booking->id, [...$payload, 'revision' => 1, 'barber_id' => 2, 'booking_date' => '2026-11-04', 'booking_time' => '14:00', 'duration' => 1])
            ->assertOk()->assertJsonPath('booking.barber_name', 'Daniel Brooks')->assertJsonPath('booking.duration', 50)->assertJsonPath('booking.revision', 2);
        $this->booking();
        $this->assertDatabaseCount('bookings', 3);
    }

    public function test_cancelled_appointments_cannot_be_reactivated_through_reschedule(): void
    {
        $booking = $this->booking();
        $booking->update(['status' => 'cancelled']);
        Sanctum::actingAs($this->admin(), ['admin']);
        $this->patchJson('/api/admin/bookings/'.$booking->id, [
            'action' => 'reschedule', 'revision' => 0, 'barber_id' => 1, 'booking_date' => '2026-11-03', 'booking_time' => '14:00',
        ])->assertUnprocessable();
        $this->assertSame('cancelled', $booking->fresh()->status);
    }
}
