<?php

namespace Tests\Feature;

use App\Mail\WebsiteInvitationEmail;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WebsiteInvitationTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $owner = User::factory()->create(['is_admin' => true]);
        $this->artisan('admin:website-owner', ['email' => $owner->email])->assertSuccessful();

        return $owner;
    }

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('website-test', ['admin'])->plainTextToken);
    }

    private function invite(User $owner, string $email, array $assignments = []): string
    {
        $link = $this->asUser($owner)->postJson('/api/admin/roles/invitations', compact('email', 'assignments'))
            ->assertCreated()->json('link');
        [$route, $query] = explode('?', parse_url($link, PHP_URL_FRAGMENT), 2);
        $this->assertSame('/admin/signup/super-admin', $route);
        parse_str($query, $parameters);
        $this->assertSame(strtolower(trim($email)), $parameters['email']);

        return $parameters['invitation'];
    }

    private function accept(string $email, string $invitation)
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->postJson('/api/admin/register-super-admin', [
            'name' => 'Invited staff', 'email' => $email, 'invitation' => $invitation,
            'password' => 'Website-Invitation-12345', 'password_confirmation' => 'Website-Invitation-12345',
            'role' => 'owner', 'assignments' => [['shop_id' => 99, 'permissions' => ['shop.manage']]],
        ]);
    }

    public function test_booking_lookup_and_sign_in_do_not_use_the_invitation_acceptance_limit(): void
    {
        $email = 'isolated-invite@example.test';
        $invitation = $this->invite($this->owner(), $email);
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/bookings/lookup', [
                'reference' => 'FF-AAAAAAAAAAAAAAAA', 'email' => 'missing@example.test',
            ])->assertNotFound();
        }
        $this->postJson('/api/bookings/lookup', [
            'reference' => 'FF-AAAAAAAAAAAAAAAA', 'email' => 'missing@example.test',
        ])->assertTooManyRequests();
        $this->postJson('/api/admin/login', [])->assertUnprocessable();
        $this->accept($email, $invitation)->assertCreated();
    }

    public function test_invitation_is_emailed_and_still_has_a_shareable_link_if_delivery_fails(): void
    {
        $owner = $this->owner();
        config([
            'booking_mail.local_mailpit' => false, 'mail.default' => 'smtp',
            'mail.from.address' => 'sender@example.test', 'mail.mailers.smtp.host' => 'smtp.example.test',
            'mail.mailers.smtp.username' => 'test', 'mail.mailers.smtp.password' => 'test',
        ]);
        Mail::fake();
        $email = 'invited@example.test';
        $link = $this->asUser($owner)->postJson('/api/admin/roles/invitations', ['email' => $email, 'assignments' => []])
            ->assertCreated()->assertJsonPath('email_delivery', 'sent')->json('link');
        Mail::assertSent(WebsiteInvitationEmail::class, fn ($mail) => $mail->hasTo($email) && $mail->link === $link);

        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Simulated SMTP failure'));
        $fallbackLink = $this->asUser($owner)->postJson('/api/admin/roles/invitations', [
            'email' => 'fallback@example.test', 'assignments' => [],
        ])->assertCreated()->assertJsonPath('email_delivery', 'failed')->json('link');
        parse_str(explode('?', parse_url($fallbackLink, PHP_URL_FRAGMENT), 2)[1], $parameters);
        $this->accept('fallback@example.test', $parameters['invitation'])->assertCreated();
    }

    public function test_owner_can_invite_and_the_new_account_receives_only_the_preselected_access(): void
    {
        $owner = $this->owner();
        $unassigned = Shop::create(['name' => 'Unassigned business', 'slug' => 'unassigned-business']);
        $email = 'NEW@example.com';
        $token = $this->invite($owner, $email, [['shop_id' => 1, 'permissions' => ['bookings.read']]]);
        $this->assertDatabaseMissing('users', ['email' => strtolower($email)]);
        $this->assertDatabaseHas('website_admin_invitations', ['email' => strtolower($email), 'token_hash' => hash('sha256', $token)]);
        $this->getJson('/api/admin/roles')->assertOk()
            ->assertJsonPath('pending_website_invitations.0.assignments.1', ['bookings.read'])
            ->assertJsonMissingPath('pending_website_invitations.0.token_hash')
            ->assertJsonMissing(['link' => $token]);

        $this->accept('wrong@example.com', $token)->assertUnprocessable()->assertJsonValidationErrors('invitation');
        $response = $this->accept($email, $token)->assertCreated()
            ->assertJsonPath('user.website_role', 'super_admin')
            ->assertJsonPath('user.permissions', ['bookings.read'])
            ->assertJsonCount(1, 'user.shops');
        $this->accept($email, $token)->assertUnprocessable()->assertJsonValidationErrors('invitation');
        $account = User::where('email', strtolower($email))->firstOrFail();
        $this->assertDatabaseMissing('shop_memberships', ['user_id' => $account->id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'website.invitation.accepted', 'subject_id' => $account->id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'website.shop_access_changed', 'subject_id' => $account->id, 'shop_id' => 1]);

        $this->app['auth']->forgetGuards();
        $this->withToken($response->json('token'))->getJson('/api/admin/bookings')->assertOk();
        $this->getJson('/api/admin/bookings', ['X-Shop-Id' => $unassigned->id])->assertForbidden();
        $this->getJson('/api/admin/messages')->assertForbidden();
        $this->getJson('/api/platform/overview')->assertOk();
        $this->asUser($owner)->getJson('/api/admin/roles')->assertJsonCount(0, 'pending_website_invitations');
    }

    public function test_only_the_owner_can_issue_or_revoke_an_invitation(): void
    {
        $owner = $this->owner();
        $email = 'pending@example.com';
        $token = $this->invite($owner, $email);
        $id = DB::table('website_admin_invitations')->where('email', $email)->value('id');
        $super = User::factory()->create(['is_admin' => true, 'website_role' => 'super_admin']);
        $this->asUser($super)->getJson('/api/admin/roles')->assertOk()->assertJsonCount(0, 'pending_website_invitations');
        $this->postJson('/api/admin/roles/invitations', ['email' => 'other@example.com', 'assignments' => []])->assertForbidden();
        $this->deleteJson('/api/admin/roles/invitations/'.$id)->assertForbidden();

        $this->asUser($owner)->deleteJson('/api/admin/roles/invitations/'.$id)->assertNoContent();
        $this->accept($email, $token)->assertUnprocessable()->assertJsonValidationErrors('invitation');
        $this->assertDatabaseMissing('users', ['email' => $email]);
        $this->assertDatabaseHas('audit_events', ['action' => 'website.invitation.revoked', 'subject_id' => $id, 'user_id' => $owner->id]);
    }

    public function test_expired_invitations_and_invalid_permissions_do_not_grant_access(): void
    {
        $owner = $this->owner();
        $email = 'pending@example.com';
        $this->asUser($owner)->postJson('/api/admin/roles/invitations', ['email' => $email,
            'assignments' => [['shop_id' => 1, 'permissions' => ['payments.record']]]])
            ->assertUnprocessable()->assertJsonValidationErrors('assignments');
        $token = $this->invite($owner, $email);
        $this->postJson('/api/admin/roles/invitations', ['email' => $email, 'assignments' => []])->assertConflict();
        $this->travel(48)->hours();
        $this->accept($email, $token)->assertUnprocessable()->assertJsonValidationErrors('invitation');
        $this->assertDatabaseMissing('users', ['email' => $email]);
    }
}
