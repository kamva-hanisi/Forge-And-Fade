<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminInboxTest extends TestCase
{
    use RefreshDatabase;

    private function member(string $role = 'owner'): User
    {
        $user = User::factory()->create();
        $this->grantShopRole($user, $role, 1, $role === 'barber' ? 1 : null);
        $this->withToken($user->createToken('inbox-test', ['admin'])->plainTextToken);

        return $user;
    }

    private function enquiry(array $attributes = []): ContactMessage
    {
        return ContactMessage::create($attributes + ['shop_id' => 1, 'name' => 'Customer Example',
            'email' => 'customer@example.com', 'phone' => null, 'message' => 'Is parking available?', 'status' => 'new']);
    }

    public function test_inbox_requires_an_authenticated_authorized_membership(): void
    {
        $message = $this->enquiry();
        $this->getJson('/api/admin/messages')->assertUnauthorized();
        $user = User::factory()->create();
        $this->withToken($user->createToken('ordinary', ['admin'])->plainTextToken);
        $this->getJson('/api/admin/messages')->assertForbidden();
        $this->member('barber');
        $this->getJson('/api/admin/messages')->assertForbidden();
        $this->patchJson('/api/admin/messages/'.$message->id, ['status' => 'read', 'expected_status' => 'new'])->assertForbidden();
    }

    public function test_owner_and_manager_can_read_and_update_without_sending_email(): void
    {
        $owner = $this->member();
        $message = $this->enquiry();
        $this->getJson('/api/admin/messages')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('data.0.id', $message->id);
        $this->patchJson('/api/admin/messages/'.$message->id, ['status' => 'read', 'expected_status' => 'new', 'email' => 'attacker@example.com', 'shop_id' => 999])
            ->assertOk()->assertJsonPath('enquiry.status', 'read');
        $this->assertDatabaseHas('contact_messages', ['id' => $message->id, 'email' => 'customer@example.com', 'shop_id' => 1]);
        $this->assertDatabaseHas('audit_events', ['user_id' => $owner->id, 'action' => 'message.status_updated', 'subject_id' => $message->id]);
        $this->member('manager');
        $this->getJson('/api/admin/messages')->assertOk();
        $this->patchJson('/api/admin/messages/'.$message->id, ['status' => 'resolved', 'expected_status' => 'read'])->assertOk();
    }

    public function test_shop_isolation_applies_to_search_counts_pages_and_updates(): void
    {
        $this->member();
        $shop = Shop::create(['name' => 'Other Shop', 'slug' => 'other-shop', 'timezone' => 'Africa/Johannesburg']);
        $foreign = $this->enquiry(['shop_id' => $shop->id, 'name' => 'Foreign Customer']);
        $own = $this->enquiry();
        $this->getJson('/api/admin/messages')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $own->id);
        $this->getJson('/api/admin/messages?search=Foreign')->assertOk()->assertJsonPath('total', 0);
        $this->patchJson('/api/admin/messages/'.$foreign->id, ['status' => 'read', 'expected_status' => 'new'])->assertNotFound();
        $this->withHeader('X-Shop-Id', $shop->id)->getJson('/api/admin/messages')->assertForbidden();
    }

    public function test_search_status_filter_order_and_pagination(): void
    {
        $this->member();
        for ($index = 0; $index < 12; $index++) {
            $this->enquiry(['name' => 'Searchable Customer']);
        }
        $resolved = $this->enquiry(['status' => 'resolved', 'message' => 'Special request']);
        $this->getJson('/api/admin/messages')->assertOk()->assertJsonPath('total', 13)->assertJsonCount(10, 'data')->assertJsonPath('data.0.id', $resolved->id);
        $this->getJson('/api/admin/messages?page=2')->assertOk()->assertJsonCount(3, 'data');
        $this->getJson('/api/admin/messages?status=new&search=Searchable')->assertOk()->assertJsonPath('total', 12);
        $this->getJson('/api/admin/messages?search=Special')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/admin/messages?status=resolved')->assertOk()->assertJsonPath('total', 1);
    }

    public function test_stale_updates_invalid_statuses_and_missing_messages_are_rejected(): void
    {
        $this->member();
        $message = $this->enquiry(['status' => 'read']);
        $this->patchJson('/api/admin/messages/'.$message->id, ['status' => 'resolved', 'expected_status' => 'new'])->assertConflict();
        $this->assertDatabaseHas('contact_messages', ['id' => $message->id, 'status' => 'read']);
        $this->patchJson('/api/admin/messages/'.$message->id, ['status' => 'deleted', 'expected_status' => 'read'])->assertUnprocessable();
        $this->patchJson('/api/admin/messages/'.$message->id, ['status' => 'resolved'])->assertUnprocessable();
        $this->patchJson('/api/admin/messages/999999', ['status' => 'read', 'expected_status' => 'new'])->assertNotFound();
        $this->getJson('/api/admin/messages?status=invalid')->assertUnprocessable();
        $this->getJson('/api/admin/messages?page=0')->assertUnprocessable();
        $this->getJson('/api/admin/messages?search='.str_repeat('x', 101))->assertUnprocessable();
    }
}
