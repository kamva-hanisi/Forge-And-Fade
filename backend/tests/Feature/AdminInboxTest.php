<?php

namespace Tests\Feature;

use App\Mail\ContactReplyEmail;
use App\Models\ContactMessage;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
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

    private function configureMail(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.test',
            'mail.mailers.smtp.username' => 'test', 'mail.mailers.smtp.password' => 'test',
            'mail.from.address' => 'shop@example.test', 'booking_mail.local_mailpit' => false, 'booking_mail.enabled' => false]);
    }

    public function test_manager_can_queue_one_reply_to_the_original_sender_and_see_delivery(): void
    {
        $manager = $this->member('manager');
        $enquiry = $this->enquiry();
        $this->configureMail();
        Mail::fake();
        $path = "/api/admin/messages/{$enquiry->id}/reply";
        $body = 'Thanks for contacting us. We can help with your visit.';
        $payload = ['body' => $body, 'request_id' => (string) Str::uuid(), 'email' => 'other@example.test'];
        $replyId = $this->postJson($path, $payload)->assertAccepted()
            ->assertJsonPath('reply.status', 'pending')->json('reply.id');
        $this->postJson($path, $payload)->assertOk()->assertJsonPath('reply.id', $replyId);
        $this->postJson($path, ['body' => 'An edited body'] + $payload)->assertConflict();
        $this->postJson($path, ['request_id' => (string) Str::uuid()] + $payload)->assertConflict();
        Mail::assertNothingSent();
        $this->assertDatabaseHas('audit_events', ['user_id' => $manager->id, 'action' => 'message.reply_queued', 'subject_id' => $enquiry->id]);

        $this->artisan('contact:send-replies')->assertSuccessful();
        $this->artisan('contact:send-replies')->assertSuccessful();
        Mail::assertSentCount(1);
        Mail::assertSent(ContactReplyEmail::class, fn ($mail) => $mail->hasTo($enquiry->email)
            && ! $mail->hasTo('other@example.test') && $mail->body === $body);
        $this->getJson('/api/admin/messages')->assertOk()
            ->assertJsonPath('data.0.status', 'new')
            ->assertJsonPath('data.0.latest_reply.body', $body)
            ->assertJsonPath('data.0.latest_reply.status', 'sent')
            ->assertJsonMissingPath('data.0.latest_reply.request_id');
    }

    public function test_reply_permissions_and_mail_failure_are_handled(): void
    {
        $enquiry = $this->enquiry();
        $path = "/api/admin/messages/{$enquiry->id}/reply";
        $payload = ['body' => 'Thanks for reaching out.', 'request_id' => (string) Str::uuid()];
        $this->postJson($path, $payload)->assertUnauthorized();
        Sanctum::actingAs($this->member('barber'), ['admin']);
        $this->postJson($path, $payload)->assertForbidden();
        Sanctum::actingAs($this->member(), ['admin']);
        $otherShop = Shop::create(['name' => 'Other Shop', 'slug' => 'other-reply-shop', 'timezone' => 'Africa/Johannesburg']);
        $foreign = $this->enquiry(['shop_id' => $otherShop->id]);
        $this->postJson("/api/admin/messages/{$foreign->id}/reply", $payload)->assertNotFound();
        $this->postJson($path, ['body' => '   '] + $payload)->assertUnprocessable();
        $this->postJson($path, $payload)->assertServiceUnavailable();

        $this->configureMail();
        $this->postJson($path, $payload)->assertAccepted();
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Internal transport details'));
        $this->artisan('contact:send-replies')->assertFailed();
        $this->getJson('/api/admin/messages')->assertOk()
            ->assertJsonPath('data.0.latest_reply.status', 'failed')
            ->assertJsonPath('data.0.latest_reply.sent_at', null)
            ->assertJsonMissing(['message' => 'Internal transport details']);
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
