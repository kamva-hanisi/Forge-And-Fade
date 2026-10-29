<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['password' => 'Original-Password-123']);
        $user->forceFill(['is_admin' => true])->save();
        $this->withToken($user->createToken('test', ['admin'])->plainTextToken);

        return $user;
    }

    public function test_settings_require_authenticated_admin_ability(): void
    {
        foreach (['dashboard', 'profile', 'invitations'] as $path) {
            $this->getJson('/api/admin/'.$path)->assertUnauthorized();
        }
        $user = User::factory()->create();
        $this->withToken($user->createToken('customer', ['admin'])->plainTextToken);
        $this->getJson('/api/admin/profile')->assertForbidden();
        $this->postJson('/api/admin/invitations', ['email' => 'member@example.com', 'send_email' => false])->assertForbidden();
    }

    public function test_profile_updates_name_but_does_not_change_email_or_role(): void
    {
        $user = $this->admin();
        $this->patchJson('/api/admin/profile', ['name' => 'Updated Name', 'email' => 'other@example.com', 'is_admin' => false])
            ->assertOk()->assertJsonPath('name', 'Updated Name')->assertJsonPath('email', $user->email)->assertJsonMissingPath('password');
        $this->assertTrue($user->fresh()->is_admin);
        $this->patchJson('/api/admin/profile', ['name' => ''])->assertUnprocessable();
    }

    public function test_password_change_requires_current_password_and_revokes_all_tokens(): void
    {
        $user = $this->admin();
        $user->createToken('other', ['admin']);
        $input = ['current_password' => 'Wrong-Password-123', 'password' => 'New-Password-12345', 'password_confirmation' => 'New-Password-12345'];
        $this->putJson('/api/admin/password', $input)->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens', 2);
        $input['current_password'] = 'Original-Password-123';
        $this->putJson('/api/admin/password', $input)->assertOk();
        $this->assertTrue(Hash::check('New-Password-12345', $user->fresh()->password));
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_private_invitation_link_registers_only_matching_email_once(): void
    {
        $this->admin();
        DB::table('admin_registration')->where('id', 1)->update(['claimed' => true]);
        $response = $this->postJson('/api/admin/invitations', ['email' => 'Member@Example.com', 'send_email' => false])->assertCreated();
        parse_str(parse_url($response->json('link'), PHP_URL_FRAGMENT) ? explode('?', parse_url($response->json('link'), PHP_URL_FRAGMENT), 2)[1] : '', $query);
        $this->assertSame('member@example.com', $query['email']);
        $this->assertDatabaseHas('admin_invitations', ['token_hash' => hash('sha256', $query['invitation'])]);
        $this->getJson('/api/admin/invitations')->assertOk()->assertJsonMissingPath('invitations.0.token_hash');
        $input = ['name' => 'Member', 'email' => 'wrong@example.com', 'password' => 'Member-Password-123', 'password_confirmation' => 'Member-Password-123', 'invitation' => $query['invitation']];
        $this->postJson('/api/admin/register', $input)->assertUnprocessable();
        $input['email'] = $query['email'];
        $this->postJson('/api/admin/register', $input)->assertCreated();
        $this->assertNotNull(DB::table('admin_invitations')->first()->used_at);
    }

    public function test_unconfigured_email_never_claims_delivery_or_creates_invitation(): void
    {
        $this->admin();
        config(['mail.default' => 'log']);
        $this->postJson('/api/admin/invitations', ['email' => 'member@example.com', 'send_email' => true])->assertStatus(503);
        $this->assertDatabaseCount('admin_invitations', 0);
    }

    public function test_smtp_failure_removes_the_unsent_invitation(): void
    {
        $this->admin();
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.username' => 'sender@example.com', 'mail.mailers.smtp.password' => 'test-only']);
        Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('Transport failed'));
        $this->postJson('/api/admin/invitations', ['email' => 'member@example.com', 'send_email' => true])->assertStatus(503);
        $this->assertDatabaseCount('admin_invitations', 0);
    }

    public function test_dashboard_uses_database_counts(): void
    {
        $this->admin();
        $this->getJson('/api/admin/dashboard')->assertOk()->assertJsonPath('today_count', 0)->assertJsonPath('upcoming_count', 0)->assertJsonPath('cancelled_count', 0)->assertJsonCount(0, 'next');
    }
}
