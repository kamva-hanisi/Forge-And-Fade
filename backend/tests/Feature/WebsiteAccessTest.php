<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WebsiteAccessTest extends TestCase
{
    use RefreshDatabase;

    private function token(User $user): string
    {
        return $user->createToken('dashboard', ['admin'])->plainTextToken;
    }

    private function asToken(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function owner(): User
    {
        $owner = User::factory()->create(['is_admin' => true]);
        $this->artisan('admin:website-owner', ['email' => $owner->email])->assertSuccessful();

        return $owner;
    }

    private function shop(): Shop
    {
        return Shop::create(['name' => 'Second Shop', 'slug' => 'second-shop']);
    }

    private function change(string $token, User $target, ?string $role, array $assignments = [], int $version = 0, bool $suspended = false)
    {
        return $this->asToken($token)->putJson('/api/admin/roles/accounts/'.$target->id, [
            'role' => $role, 'version' => $version, 'suspended' => $suspended,
            'assignments' => collect($assignments)->map(fn ($permissions, $shop) => [
                'shop_id' => $shop, 'permissions' => $permissions,
            ])->values()->all(),
        ]);
    }

    public function test_owner_can_grant_business_scoped_payment_viewing_and_recording_separately(): void
    {
        $ownerToken = $this->token($this->owner());
        $other = $this->shop();
        $booking = $this->asToken($ownerToken)->postJson('/api/admin/bookings', [
            'service_id' => 2, 'barber_id' => 1, 'booking_date' => now()->next('Tuesday')->toDateString(),
            'booking_time' => '10:00', 'customer_name' => 'Receipt customer',
            'email' => 'receipt@example.test', 'phone' => '0712345678',
        ])->assertCreated()->json('booking');
        $receipt = ['amount' => '250.00', 'method' => 'cash', 'received_on' => now('Africa/Johannesburg')->toDateString()];
        foreach (['super_admin', 'admin'] as $role) {
            $staff = User::factory()->create();
            $staffToken = $this->token($staff);
            $this->change($ownerToken, $staff, $role, [1 => ['bookings.read', 'reports.read']])->assertOk();
            $this->asToken($staffToken)->getJson('/api/admin/payments')->assertForbidden();
            $this->asToken($staffToken)->getJson('/api/admin/bookings')->assertOk()
                ->assertJsonMissingPath('data.0.online_paid_at')->assertJsonMissingPath('data.0.active_payment');
            $this->change($ownerToken, $staff, $role, [1 => ['payments.read']], 1)->assertOk();
            $this->asToken($staffToken)->getJson('/api/admin/payments')->assertOk();
            $this->asToken($staffToken)->getJson('/api/admin/payments', ['X-Shop-Id' => $other->id])->assertForbidden();
            $this->asToken($staffToken)->postJson('/api/admin/bookings/'.$booking['id'].'/payments', $receipt)->assertForbidden();
            $this->change($ownerToken, $staff, $role, [1 => ['bookings.read', 'payments.record']], 2)->assertUnprocessable();
            $this->change($ownerToken, $staff, $role, [1 => ['bookings.read', 'payments.read', 'payments.record']], 2)->assertOk();
            $payment = $this->asToken($staffToken)->postJson('/api/admin/bookings/'.$booking['id'].'/payments', $receipt)->assertCreated()->json('payment');
            $this->asToken($staffToken)->postJson('/api/admin/payments/'.$payment['id'].'/void', ['reason' => 'Incorrect receipt'])->assertForbidden();
            $this->asToken($ownerToken)->postJson('/api/admin/payments/'.$payment['id'].'/void', ['reason' => 'Incorrect receipt'])->assertOk();
        }
    }

    public function test_owner_is_explicit_unique_and_legacy_platform_grants_require_review(): void
    {
        $legacy = User::factory()->create(['is_admin' => true, 'is_platform_admin' => true]);
        $legacyToken = $this->token($legacy);
        $this->asToken($legacyToken)->getJson('/api/platform/overview')->assertOk();
        $owner = $this->owner();
        $this->artisan('admin:website-owner', ['email' => $legacy->email])->assertFailed();
        $this->artisan('admin:platform', ['email' => $legacy->email])->assertFailed();
        $this->asToken($legacyToken)->getJson('/api/platform/overview')->assertForbidden();
        $ownerToken = $this->token($owner);
        $this->asToken($ownerToken)->getJson('/api/admin/roles')
            ->assertOk()->assertJsonPath('identity.website_role', 'owner')
            ->assertJsonPath('accounts.0.legacy_platform', true)
            ->assertJsonPath('activity.data.0.action', 'website.owner_initialized');
        $this->change($ownerToken, $owner, null)->assertForbidden();

        $second = $this->shop();
        $this->asToken($ownerToken)->getJson('/api/admin/session', ['X-Shop-Id' => $second->id])
            ->assertOk()->assertJsonPath('user.shop.id', $second->id);
        $this->change($ownerToken, $legacy, 'super_admin')->assertOk();
        $this->asToken($legacyToken)->getJson('/api/platform/overview')->assertOk();
        $this->asToken($legacyToken)->getJson('/api/admin/session')
            ->assertOk()->assertJsonPath('user.shop', null);
        $this->asToken($legacyToken)->getJson('/api/admin/bookings', ['X-Shop-Id' => 1])->assertForbidden();
    }

    public function test_super_admin_can_delegate_only_to_admins_in_their_scope(): void
    {
        $owner = $this->owner();
        $ownerToken = $this->token($owner);
        $super = User::factory()->create();
        $target = User::factory()->create();
        $peer = User::factory()->create();
        $unscoped = User::factory()->create();
        $other = $this->shop();
        $this->change($ownerToken, $super, 'super_admin', [1 => ['bookings.read', 'shop.manage']])->assertOk();
        $this->change($ownerToken, $peer, 'super_admin')->assertOk();
        $this->change($ownerToken, $unscoped, 'admin', [$other->id => ['bookings.read']])->assertOk();

        $superToken = $this->token($super);
        $this->asToken($superToken)->getJson('/api/admin/roles')->assertOk()->assertJsonCount(0, 'accounts')
            ->assertJsonCount(1, 'shop_options')->assertJsonPath('shop_options.0.delegable', ['bookings.read']);
        $this->asToken($superToken)->getJson('/api/admin/roles/account?'.http_build_query(['email' => $target->email]))
            ->assertOk()->assertJsonPath('account.id', $target->id);
        $this->change($superToken, $target, 'super_admin')->assertForbidden();
        $this->change($superToken, $target, 'admin', [1 => ['shop.manage']])->assertForbidden();
        $this->change($superToken, $target, 'admin', [1 => ['messages.manage']])->assertForbidden();
        $this->change($superToken, $target, 'admin', [$other->id => ['bookings.read']])->assertForbidden();
        $this->change($superToken, $peer, null)->assertForbidden();
        $this->change($superToken, $unscoped, null)->assertForbidden();
        $this->change($superToken, $target, 'admin', [1 => ['bookings.read']])->assertOk();
        $this->asToken($superToken)->getJson('/api/admin/management')
            ->assertOk()->assertJsonCount(0, 'members');
        $this->asToken($superToken)->getJson('/api/admin/invitations')->assertForbidden();
        $this->asToken($superToken)->deleteJson('/api/admin/management/members/1', ['version' => 0])->assertForbidden();
        $this->asToken($superToken)->getJson('/api/admin/roles')
            ->assertOk()->assertJsonPath('accounts.0.id', $target->id)
            ->assertJsonMissing(['subject_id' => $unscoped->id]);
    }

    public function test_admin_scope_and_permission_changes_are_enforced_for_existing_tokens_and_audited(): void
    {
        $owner = $this->owner();
        $ownerToken = $this->token($owner);
        $admin = User::factory()->create();
        $other = $this->shop();
        $this->change($ownerToken, $admin, 'admin', [1 => ['bookings.read']])->assertOk();
        $adminToken = $this->token($admin);
        $this->asToken($adminToken)->getJson('/api/admin/bookings')->assertOk();
        $this->asToken($adminToken)->getJson('/api/admin/bookings', ['X-Shop-Id' => $other->id])->assertForbidden();
        $this->asToken($adminToken)->getJson('/api/platform/overview')->assertForbidden();
        $this->asToken($adminToken)->getJson('/api/admin/messages')->assertForbidden();
        $this->asToken($adminToken)->getJson('/api/admin/roles')
            ->assertOk()->assertJsonPath('can_manage', false)->assertJsonCount(0, 'activity.data');
        $this->change($adminToken, $admin, 'super_admin')->assertForbidden();
        $this->change($ownerToken, $admin, 'admin', [1 => ['messages.manage']])->assertConflict();

        $this->change($ownerToken, $admin, 'admin', [1 => ['messages.manage']], 1)->assertOk();
        $this->asToken($adminToken)->getJson('/api/admin/session')->assertOk()
            ->assertJsonPath('user.permissions', ['messages.manage']);
        $this->asToken($adminToken)->getJson('/api/admin/messages')->assertOk();
        $this->asToken($adminToken)->getJson('/api/admin/bookings')->assertForbidden();
        $this->asToken($adminToken)->getJson('/api/admin/bookings/catalog')->assertForbidden();
        $this->change($ownerToken, $admin, 'admin', [1 => ['messages.manage']], 2, true)->assertOk();
        $this->asToken($adminToken)->getJson('/api/admin/messages')->assertForbidden();
        $this->change($ownerToken, $admin, 'admin', [1 => ['messages.manage']], 3)->assertOk();
        $this->asToken($adminToken)->getJson('/api/admin/messages')->assertOk();
        $this->asToken($ownerToken)->getJson('/api/admin/roles')->assertOk()
            ->assertJsonPath('activity.data.0.shop_id', 1)
            ->assertJsonPath('activity.data.0.user_id', $owner->id)
            ->assertJsonPath('activity.data.0.subject_id', $admin->id)
            ->assertJsonPath('activity.data.0.details.before.suspended', true)
            ->assertJsonPath('activity.data.0.details.after.suspended', false);
    }

    public function test_website_access_can_be_removed_without_removing_existing_shop_staff_access(): void
    {
        $owner = $this->owner();
        $ownerToken = $this->token($owner);
        $staff = User::factory()->create();
        $this->grantShopRole($staff, 'manager');
        $other = $this->shop();
        $this->change($ownerToken, $staff, 'admin', [$other->id => ['bookings.read']])->assertOk();
        $staffToken = $this->token($staff);
        $this->asToken($staffToken)->getJson('/api/admin/session', ['X-Shop-Id' => $other->id])
            ->assertOk()->assertJsonPath('user.shop.id', $other->id);
        $this->change($ownerToken, $staff, null, version: 1)->assertOk();
        $this->asToken($staffToken)->getJson('/api/admin/session', ['X-Shop-Id' => $other->id])->assertForbidden();
        $this->asToken($staffToken)->getJson('/api/admin/session')
            ->assertOk()->assertJsonPath('user.role', 'manager')->assertJsonPath('user.website_role', null);
        $this->asToken($staffToken)->getJson('/api/admin/bookings')->assertOk();
        $this->assertDatabaseHas('shop_memberships', ['user_id' => $staff->id, 'shop_id' => 1, 'role' => 'manager']);
        $this->assertSame(0, DB::table('website_shop_access')->where('user_id', $staff->id)->count());
    }
}
