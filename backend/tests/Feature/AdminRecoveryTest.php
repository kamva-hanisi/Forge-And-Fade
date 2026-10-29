<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AdminRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.username' => 'test@example.com', 'mail.mailers.smtp.password' => 'test-only']);
        Notification::fake();
    }

    private function admin(): User
    {
        $user = User::factory()->create(['email' => 'owner@example.com', 'password' => 'Original-Password-123']);
        $this->grantShopRole($user);

        return $user;
    }

    private function resetInput(User $user, ?string $token = null): array
    {
        return ['email' => $user->email, 'token' => $token ?? Password::broker('users')->createToken($user),
            'password' => 'Replacement-Password-123', 'password_confirmation' => 'Replacement-Password-123'];
    }

    public function test_recovery_is_private_and_tokens_are_hashed_and_throttled(): void
    {
        $user = $this->admin();
        $known = $this->postJson('/api/admin/forgot-password', ['email' => ' OWNER@EXAMPLE.COM '])->assertOk();
        $unknown = $this->postJson('/api/admin/forgot-password', ['email' => 'unknown@example.com'])->assertOk();
        $this->assertSame($known->json(), $unknown->json());
        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $stored = DB::table('password_reset_tokens')->where('email', $user->email)->value('token');
            $this->assertNotSame($notification->token, $stored);
            $this->assertTrue(Hash::check($notification->token, $stored));
            $mail = $notification->toMail($user);
            $this->assertStringStartsWith('http://localhost:5173/#/admin/reset-password?', $mail->actionUrl);
            $this->assertStringContainsString('email=owner%40example.com', $mail->actionUrl);

            return true;
        });
        $this->postJson('/api/admin/forgot-password', ['email' => $user->email])->assertOk();
        Notification::assertSentToTimes($user, ResetPassword::class, 1);
        $this->postJson('/api/admin/forgot-password', ['email' => $user->email])->assertTooManyRequests();
    }

    public function test_unconfigured_email_is_honest_and_never_logs_reset_links(): void
    {
        $this->admin();
        config(['mail.default' => 'log']);
        $this->postJson('/api/admin/forgot-password', ['email' => 'owner@example.com'])->assertStatus(503);
        $this->assertDatabaseCount('password_reset_tokens', 0);
        Notification::assertNothingSent();
    }

    public function test_delivery_failure_invalidates_the_unsent_token(): void
    {
        $user = $this->admin();
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('Transport unavailable'));
        $this->postJson('/api/admin/forgot-password', ['email' => $user->email])->assertStatus(503);
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_reset_revokes_sessions_is_single_use_and_does_not_change_roles(): void
    {
        $user = $this->admin();
        $session = $user->createToken('old-device', ['admin'])->plainTextToken;
        $input = $this->resetInput($user);
        $this->postJson('/api/admin/reset-password', $input + ['is_platform_admin' => true, 'role' => 'platform_admin'])->assertOk()->assertJsonMissingPath('token');
        $this->assertTrue(Hash::check($input['password'], $user->fresh()->password));
        $this->assertFalse($user->fresh()->is_platform_admin);
        $this->assertDatabaseHas('shop_memberships', ['user_id' => $user->id, 'role' => 'owner']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->assertDatabaseHas('audit_events', ['user_id' => $user->id, 'action' => 'password.reset']);
        $this->postJson('/api/admin/reset-password', $input)->assertUnprocessable();
        $this->withToken($session)->getJson('/api/admin/profile')->assertUnauthorized();
        $this->postJson('/api/admin/login', ['email' => $user->email, 'password' => $input['password']])->assertOk();
    }

    public function test_invalid_expired_and_wrong_email_links_cannot_reset(): void
    {
        $user = $this->admin();
        $input = $this->resetInput($user);
        $this->postJson('/api/admin/reset-password', array_replace($input, ['email' => 'unknown@example.com']))->assertUnprocessable();
        $this->postJson('/api/admin/reset-password', array_replace($input, ['token' => str_repeat('a', 64)]))->assertUnprocessable();
        $this->travel(61)->minutes();
        $this->postJson('/api/admin/reset-password', $input)->assertUnprocessable();
        $this->assertTrue(Hash::check('Original-Password-123', $user->fresh()->password));
    }

    public function test_non_admin_and_disabled_accounts_cannot_recover_or_gain_access(): void
    {
        $user = User::factory()->create();
        $input = $this->resetInput($user);
        $this->postJson('/api/admin/forgot-password', ['email' => $user->email])->assertOk();
        Notification::assertNothingSent();
        $this->postJson('/api/admin/reset-password', $input)->assertUnprocessable();
        $this->assertFalse($user->fresh()->is_admin);
    }

    public function test_validation_and_reset_attempt_limit(): void
    {
        $user = $this->admin();
        $input = $this->resetInput($user);
        $this->postJson('/api/admin/forgot-password', ['email' => 'invalid'])->assertUnprocessable();
        $this->postJson('/api/admin/reset-password', array_replace($input, ['password_confirmation' => 'different']))->assertUnprocessable();
        $this->postJson('/api/admin/reset-password', array_replace($input, ['password' => 'short', 'password_confirmation' => 'short']))->assertUnprocessable();
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->postJson('/api/admin/reset-password', array_replace($input, ['token' => str_repeat('a', 64)]))->assertUnprocessable();
        }
        $this->postJson('/api/admin/reset-password', $input)->assertTooManyRequests();
    }

    public function test_disabling_an_admin_invalidates_an_existing_recovery_link(): void
    {
        $user = $this->admin();
        $input = $this->resetInput($user);
        $user->forceFill(['is_admin' => false])->save();
        $this->postJson('/api/admin/reset-password', $input)->assertUnprocessable();
        $this->assertTrue(Hash::check('Original-Password-123', $user->fresh()->password));
    }

    public function test_recovery_does_not_restore_a_revoked_membership(): void
    {
        $user = $this->admin();
        $input = $this->resetInput($user);
        DB::table('shop_memberships')->where('user_id', $user->id)->delete();
        $this->postJson('/api/admin/reset-password', $input)->assertOk();
        $this->postJson('/api/admin/login', ['email' => $user->email, 'password' => $input['password']])->assertUnprocessable();
        $this->assertDatabaseCount('shop_memberships', 0);
    }

    public function test_authenticated_password_change_invalidates_recovery_links(): void
    {
        $user = $this->admin();
        $input = $this->resetInput($user);
        $this->withToken($user->createToken('device', ['admin'])->plainTextToken)
            ->putJson('/api/admin/password', ['current_password' => 'Original-Password-123',
                'password' => 'New-Authenticated-123', 'password_confirmation' => 'New-Authenticated-123'])->assertOk();
        $this->postJson('/api/admin/reset-password', $input)->assertUnprocessable();
    }

    public function test_malformed_email_inputs_return_validation_errors_not_server_errors(): void
    {
        $this->postJson('/api/admin/forgot-password', ['email' => ['owner@example.com']])->assertUnprocessable();
        $this->postJson('/api/admin/reset-password', ['email' => ['owner@example.com']])->assertUnprocessable();
    }
}
