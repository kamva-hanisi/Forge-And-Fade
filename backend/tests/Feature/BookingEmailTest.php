<?php

namespace Tests\Feature;

use App\Mail\BookingEmail;
use App\Models\Booking;
use App\Models\Shop;
use App\Services\BookingEmails;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BookingEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-02 09:00:00', 'Africa/Johannesburg'));
        config(['booking_mail.enabled' => true, 'mail.default' => 'smtp', 'mail.mailers.smtp.host' => 'smtp.example.test',
            'mail.mailers.smtp.username' => 'test', 'mail.mailers.smtp.password' => 'test', 'mail.from.address' => 'shop@example.test']);
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function book(): array
    {
        return $this->postJson('/api/bookings', ['service_id' => 2, 'barber_id' => 1, 'booking_date' => '2026-11-03',
            'booking_time' => '10:00', 'customer_name' => 'Email QA', 'email' => 'email-qa@example.com', 'phone' => '0712345678'])
            ->assertCreated()->json('booking');
    }

    public function test_confirmation_is_persisted_without_sending_during_booking_and_sent_only_once(): void
    {
        $booking = $this->book();
        $this->assertDatabaseHas('booking_emails', ['booking_id' => $booking['id'], 'kind' => 'confirmation', 'status' => 'pending']);
        Mail::assertNothingSent();
        $this->artisan('bookings:notify')->assertSuccessful();
        $this->artisan('bookings:notify')->assertSuccessful();
        Mail::assertSent(BookingEmail::class, fn ($mail) => $mail->hasTo('email-qa@example.com') && $mail->kind === 'confirmation');
        Mail::assertSentCount(1);
        $this->assertDatabaseHas('booking_emails', ['booking_id' => $booking['id'], 'status' => 'sent', 'attempts' => 1]);
    }

    public function test_disabled_or_log_mail_never_sends_or_claims_real_delivery(): void
    {
        config(['booking_mail.enabled' => false]);
        $this->book();
        $this->assertDatabaseCount('booking_emails', 0);
        $this->artisan('bookings:notify')->assertFailed();
        config(['booking_mail.enabled' => true, 'mail.default' => 'log']);
        BookingEmails::record(Booking::firstOrFail(), 'confirmation');
        $this->artisan('bookings:notify')->assertFailed();
        Mail::assertNothingSent();
        $this->assertDatabaseHas('booking_emails', ['status' => 'pending', 'attempts' => 0]);
    }

    public function test_rolled_back_booking_does_not_leave_an_email(): void
    {
        DB::beginTransaction();
        $this->book();
        $this->assertDatabaseCount('booking_emails', 1);
        DB::rollBack();
        $this->assertDatabaseCount('booking_emails', 0);
        $this->assertDatabaseCount('bookings', 0);
        Mail::assertNothingSent();
    }

    public function test_reminder_is_due_in_shop_timezone_and_not_duplicated(): void
    {
        $this->book();
        $this->artisan('bookings:notify')->assertSuccessful();
        Carbon::setTestNow(Carbon::parse('2026-11-02 10:01:00', 'Africa/Johannesburg'));
        $this->artisan('bookings:notify')->assertSuccessful();
        $this->artisan('bookings:notify')->assertSuccessful();
        Mail::assertSent(BookingEmail::class, fn ($mail) => $mail->kind === 'reminder');
        Mail::assertSentCount(2);
        $this->assertDatabaseCount('booking_emails', 2);
    }

    public function test_cancellation_skips_stale_confirmation_and_reminders_and_sends_cancellation(): void
    {
        $booking = $this->book();
        $this->postJson('/api/bookings/cancel', ['reference' => $booking['reference'], 'email' => 'email-qa@example.com', 'revision' => 0])->assertOk();
        Carbon::setTestNow(Carbon::parse('2026-11-02 11:00:00', 'Africa/Johannesburg'));
        $this->artisan('bookings:notify')->assertSuccessful();
        Mail::assertSent(BookingEmail::class, fn ($mail) => $mail->kind === 'cancelled');
        Mail::assertSentCount(1);
        $this->assertDatabaseHas('booking_emails', ['kind' => 'confirmation', 'status' => 'skipped']);
        $this->assertDatabaseMissing('booking_emails', ['kind' => 'reminder']);
    }

    public function test_rescheduling_invalidates_old_email_and_past_appointments_are_skipped(): void
    {
        $this->book();
        $booking = Booking::firstOrFail();
        $booking->forceFill(['booking_time' => '11:00', 'revision' => 1])->save();
        $this->artisan('bookings:notify')->assertSuccessful();
        Mail::assertSent(BookingEmail::class, fn ($mail) => $mail->kind === 'changed' && substr($mail->booking->booking_time, 0, 5) === '11:00');
        Mail::assertSentCount(1);
        BookingEmails::record($booking, 'reminder');
        Carbon::setTestNow(Carbon::parse('2026-11-03 11:00:00', 'Africa/Johannesburg'));
        $this->artisan('bookings:notify')->assertSuccessful();
        Mail::assertSentCount(1);
        $this->assertDatabaseHas('booking_emails', ['kind' => 'reminder', 'status' => 'skipped']);
    }

    public function test_transport_failure_is_retryable_without_touching_booking(): void
    {
        $this->book();
        $fake = Mail::getFacadeRoot();
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('Sensitive transport detail'));
        $this->artisan('bookings:notify')->assertFailed();
        $this->assertDatabaseHas('booking_emails', ['status' => 'failed', 'attempts' => 1]);
        $this->assertDatabaseHas('bookings', ['status' => 'confirmed']);
        Mail::swap($fake);
        $this->artisan('bookings:notify')->assertSuccessful();
        Mail::assertNothingSent();
        Carbon::setTestNow(now()->addMinutes(5));
        $this->artisan('bookings:notify')->assertSuccessful();
        Mail::assertSentCount(1);
        $this->assertDatabaseHas('booking_emails', ['status' => 'sent', 'attempts' => 2]);
    }

    public function test_repeated_transport_failures_stop_after_three_attempts(): void
    {
        $this->book();
        Mail::shouldReceive('to')->times(3)->andThrow(new \RuntimeException('Transport failed'));
        for ($attempt = 0; $attempt < 4; $attempt++) {
            $this->artisan('bookings:notify')->assertFailed();
            Carbon::setTestNow(now()->addMinutes(5));
        }
        $this->assertDatabaseHas('booking_emails', ['status' => 'failed', 'attempts' => 3]);
    }

    public function test_short_notice_bookings_do_not_get_an_immediate_reminder(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-03 09:00:00', 'Africa/Johannesburg'));
        $this->book();
        $this->artisan('bookings:notify')->assertSuccessful();
        Mail::assertSentCount(1);
        $this->assertDatabaseMissing('booking_emails', ['kind' => 'reminder']);
    }

    public function test_template_uses_booking_shop_and_does_not_put_credentials_in_manage_url(): void
    {
        $this->book();
        $shop = Shop::create(['name' => 'Other Shop', 'slug' => 'other-shop', 'timezone' => 'Africa/Johannesburg']);
        $booking = Booking::firstOrFail();
        $booking->shop_id = $shop->id;
        $booking->save();
        $email = new BookingEmail($booking, $shop, 'confirmation');
        $text = $email->render();
        $this->assertStringContainsString('Other Shop', $text);
        $this->assertStringContainsString('/#/shops/other-shop/manage', $text);
        $this->assertStringContainsString($booking->reference, $text);
        $this->assertStringNotContainsString('email-qa@example.com', $text);
        $this->assertStringNotContainsString('reference=', $text);
        $this->assertStringContainsString('Africa/Johannesburg', $text);
    }
}
