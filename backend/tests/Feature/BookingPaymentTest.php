<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BookingPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-02 09:00:00', 'Africa/Johannesburg'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function member(string $role = 'owner', int $shop = 1): void
    {
        $user = User::factory()->create();
        $this->grantShopRole($user, $role, $shop, $role === 'barber' ? 1 : null);
        app('auth')->forgetGuards();
        $this->withToken($user->createToken('payment-test', ['admin'])->plainTextToken);
    }

    private function booking(): array
    {
        return $this->postJson('/api/admin/bookings', ['service_id' => 2, 'barber_id' => 1, 'booking_date' => '2026-11-03', 'booking_time' => '10:00',
            'customer_name' => 'Payment QA', 'email' => 'payment@example.com', 'phone' => '0712345678'])->assertCreated()->json('booking');
    }

    private function receipt(array $changes = []): array
    {
        return $changes + ['amount' => '250.00', 'method' => 'cash', 'received_on' => '2026-11-02'];
    }

    public function test_receipts_are_distinct_from_booked_value_and_duplicate_receipts_are_rejected(): void
    {
        $this->member();
        $booking = $this->booking();
        $this->getJson('/api/admin/dashboard')->assertOk()->assertJsonCount(0, 'statistics.recorded_receipts');
        $payment = $this->postJson('/api/admin/bookings/'.$booking['id'].'/payments', $this->receipt(['currency' => 'USD', 'shop_id' => 99]))
            ->assertCreated()->assertJsonPath('payment.currency', 'ZAR')->assertJsonPath('payment.shop_id', 1)->json('payment');
        $this->postJson('/api/admin/bookings/'.$booking['id'].'/payments', $this->receipt())->assertConflict();
        $response = $this->getJson('/api/admin/dashboard')->assertOk()->assertJsonPath('statistics.recorded_receipts.0.receipt_count', 1);
        $this->assertEquals('250.00', $response->json('statistics.recorded_receipts.0.amount'));
        $this->getJson('/api/admin/bookings')->assertOk()->assertJsonPath('data.0.active_payment.id', $payment['id']);
    }

    public function test_void_retains_original_history_and_allows_a_corrected_new_receipt(): void
    {
        $this->member();
        $booking = $this->booking();
        $payment = $this->postJson('/api/admin/bookings/'.$booking['id'].'/payments', $this->receipt())->assertCreated()->json('payment');
        $this->postJson('/api/admin/payments/'.$payment['id'].'/void', ['reason' => 'Incorrect test receipt'])->assertOk();
        $this->postJson('/api/admin/payments/'.$payment['id'].'/void', ['reason' => 'Again'])->assertConflict();
        $this->getJson('/api/admin/dashboard')->assertOk()->assertJsonCount(0, 'statistics.recorded_receipts');
        $this->postJson('/api/admin/bookings/'.$booking['id'].'/payments', $this->receipt(['amount' => '200.00']))->assertCreated();
        $this->assertDatabaseCount('booking_payments', 2);
        $this->assertDatabaseHas('booking_payments', ['id' => $payment['id'], 'amount' => '250.00', 'void_reason' => 'Incorrect test receipt']);
    }

    public function test_barber_and_other_shops_cannot_record_or_read_payment_information(): void
    {
        $this->member();
        $booking = $this->booking();
        $this->member('barber');
        $this->postJson('/api/admin/bookings/'.$booking['id'].'/payments', $this->receipt())->assertForbidden();
        $this->getJson('/api/admin/bookings')->assertOk()->assertJsonMissingPath('data.0.active_payment');
        $shop = Shop::create(['name' => 'Foreign', 'slug' => 'foreign-payments', 'timezone' => 'Africa/Johannesburg']);
        $this->member('owner', $shop->id);
        $this->postJson('/api/admin/bookings/'.$booking['id'].'/payments', $this->receipt())->assertNotFound();
        $this->getJson('/api/admin/dashboard')->assertOk()->assertJsonCount(0, 'statistics.recorded_receipts');
    }

    public function test_cancelled_appointments_and_invalid_receipts_are_rejected_and_manager_cannot_void(): void
    {
        $this->member('manager');
        $booking = $this->booking();
        foreach ([['amount' => 0], ['amount' => '1.234'], ['method' => 'unknown'], ['received_on' => '2026-11-03']] as $change) {
            $this->postJson('/api/admin/bookings/'.$booking['id'].'/payments', $this->receipt($change))->assertUnprocessable();
        }
        $payment = $this->postJson('/api/admin/bookings/'.$booking['id'].'/payments', $this->receipt())->assertCreated()->json('payment');
        $this->postJson('/api/admin/payments/'.$payment['id'].'/void', ['reason' => 'Not permitted'])->assertForbidden();
        $this->patchJson('/api/admin/bookings/'.$booking['id'], ['action' => 'cancel', 'revision' => 0])->assertOk();
        $this->getJson('/api/admin/dashboard')->assertOk()->assertJsonPath('statistics.recorded_receipts.0.receipt_count', 1);
    }

    public function test_appointment_quick_views_respect_start_time_and_shop(): void
    {
        $this->member();
        $booking = $this->booking();
        $this->getJson('/api/admin/bookings?view=today')->assertOk()->assertJsonPath('total', 0);
        $this->getJson('/api/admin/bookings?view=upcoming')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/admin/bookings?view=past')->assertOk()->assertJsonPath('total', 0);
        Carbon::setTestNow(Carbon::parse('2026-11-03 11:00:00', 'Africa/Johannesburg'));
        $this->getJson('/api/admin/bookings?view=past')->assertOk()->assertJsonPath('data.0.id', $booking['id']);
        $this->getJson('/api/admin/bookings?view=invalid')->assertUnprocessable();
    }
}
