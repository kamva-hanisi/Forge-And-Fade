<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $changes = []): array
    {
        return [...[
            'name' => 'Shop Owner', 'email' => 'owner@example.com',
            'password' => 'Owner-Password-12345', 'password_confirmation' => 'Owner-Password-12345',
        ], ...$changes];
    }

    public function test_first_owner_can_register_and_access_the_admin_dashboard(): void
    {
        $this->getJson('/api/admin/registration')->assertOk()->assertJsonPath('requires_invitation', false);
        $response = $this->postJson('/api/admin/register', $this->payload())->assertCreated()->assertJsonMissingPath('user.password');
        $owner = User::firstOrFail();
        $this->assertTrue($owner->is_admin);
        $this->assertTrue(Hash::check('Owner-Password-12345', $owner->password));
        $this->assertDatabaseHas('admin_registration', ['id' => 1, 'claimed' => true]);
        $this->withToken($response->json('token'))->getJson('/api/admin/bookings')->assertOk();
    }

    public function test_first_owner_setup_never_reopens_after_admin_is_demoted(): void
    {
        $this->postJson('/api/admin/register', $this->payload())->assertCreated();
        User::firstOrFail()->forceFill(['is_admin' => false])->save();
        $this->getJson('/api/admin/registration')->assertJsonPath('requires_invitation', true);
        $this->postJson('/api/admin/register', $this->payload(['email' => 'another@example.com']))->assertUnprocessable()->assertJsonValidationErrors('invitation');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_existing_admin_requires_invitation_and_matching_email(): void
    {
        User::factory()->create()->forceFill(['is_admin' => true])->save();
        $this->postJson('/api/admin/register', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('invitation');
        DB::table('admin_invitations')->insert([
            'email' => 'owner@example.com', 'token_hash' => hash('sha256', str_repeat('a', 64)), 'expires_at' => now()->addDay(),
        ]);
        $this->postJson('/api/admin/register', $this->payload(['email' => 'wrong@example.com', 'invitation' => str_repeat('a', 64)]))->assertUnprocessable()->assertJsonValidationErrors('invitation');
        $this->postJson('/api/admin/register', $this->payload(['invitation' => str_repeat('a', 64)]))->assertCreated();
        $this->assertNotNull(DB::table('admin_invitations')->first()->used_at);
        $this->travel(61)->seconds();
        User::where('email', 'owner@example.com')->delete();
        $this->postJson('/api/admin/register', $this->payload(['invitation' => str_repeat('a', 64)]))->assertUnprocessable()->assertJsonValidationErrors('invitation');
    }

    public function test_expired_invitation_and_password_mismatch_are_rejected(): void
    {
        $this->postJson('/api/admin/register', $this->payload(['password_confirmation' => 'Different-password-123']))->assertUnprocessable()->assertJsonValidationErrors('password');
        User::factory()->create()->forceFill(['is_admin' => true])->save();
        DB::table('admin_invitations')->insert([
            'email' => 'owner@example.com', 'token_hash' => hash('sha256', str_repeat('b', 64)), 'expires_at' => now()->subMinute(),
        ]);
        $this->postJson('/api/admin/register', $this->payload(['invitation' => str_repeat('b', 64)]))->assertUnprocessable()->assertJsonValidationErrors('invitation');
    }

    public function test_invitation_command_stores_only_a_hash_and_customer_registration_is_removed(): void
    {
        $this->artisan('admin:invite', ['email' => 'staff@example.com'])->assertExitCode(0);
        $invitation = DB::table('admin_invitations')->first();
        $this->assertSame('staff@example.com', $invitation->email);
        $this->assertSame(64, strlen($invitation->token_hash));
        $this->postJson('/api/auth/register', $this->payload())->assertNotFound();
        $this->postJson('/api/auth/login', $this->payload())->assertNotFound();
    }

    public function test_admin_signup_is_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->postJson('/api/admin/register', [])->assertUnprocessable();
        }
        $this->postJson('/api/admin/register', [])->assertTooManyRequests();
    }
}
