<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_send_a_contact_message(): void
    {
        $response = $this->postJson('/api/contact', [
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
            'phone' => '+27 82 555 0100',
            'message' => 'Do you accept walk-in appointments?',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', 'Thank you. Your message has been sent successfully.')
            ->assertJsonPath('contact.status', 'new');

        $this->assertDatabaseHas('contact_messages', [
            'email' => 'customer@example.com',
            'status' => 'new',
        ]);
    }

    public function test_contact_message_requires_name_email_and_message(): void
    {
        $this->postJson('/api/contact', [
            'email' => 'not-an-email',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'message']);
    }
}
