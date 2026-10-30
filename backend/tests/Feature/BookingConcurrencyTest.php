<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class BookingConcurrencyTest extends TestCase
{
    public static function competingTimes(): array
    {
        return [
            'identical starts' => [['10:00', '10:00']],
            'any barber capacity' => [['10:00', '10:00'], 'any'],
            'specific and any capacity' => [['10:00', '10:00'], 'specific_any'],
            'overlapping starts' => [['10:00', '10:20']],
            'staff and public identical' => [['10:00', '10:00'], 'mixed'],
            'staff and public overlapping' => [['10:00', '10:20'], 'mixed'],
            'staff and staff identical' => [['10:00', '10:00'], 'staff'],
            'staff and staff overlapping' => [['10:00', '10:20'], 'staff'],
            'schedule edit and public booking' => [['10:20', '10:20'], 'schedule_public'],
            'schedule edit and staff booking' => [['10:20', '10:20'], 'schedule_staff'],
            'business hours edit and public booking' => [['10:20', '10:20'], 'business_public'],
            'business hours edit and staff booking' => [['10:20', '10:20'], 'business_staff'],
        ];
    }

    #[DataProvider('competingTimes')]
    public function test_competing_requests_for_an_empty_day_create_only_one_booking(array $times, string $mode = 'public'): void
    {
        if (getenv('BOOKING_MYSQL_CONCURRENCY') !== '1') {
            $this->markTestSkipped('Set BOOKING_MYSQL_CONCURRENCY=1 and use a dedicated MySQL test database.');
        }
        $this->assertSame('mysql', DB::getDriverName());
        $this->assertStringEndsWith('_test', DB::connection()->getDatabaseName());
        Artisan::call('migrate', ['--force' => true]);
        $date = '2099-11-03';
        DB::table('bookings')->where('booking_date', $date)->delete();
        DB::table('booking_day_locks')->where('booking_date', $date)->delete();
        DB::table('shop_booking_day_locks')->where('shop_id', 1)->where('booking_date', $date)->delete();
        $processes = [];
        $user = null;
        $scheduling = str_starts_with($mode, 'schedule_') || str_starts_with($mode, 'business_');
        $originalSettings = DB::table('shops')->where('id', 1)->value('settings');
        $barrier = tempnam(sys_get_temp_dir(), 'booking-race-');
        unlink($barrier);
        try {
            if ($scheduling) {
                DB::table('shops')->where('id', 1)->update(['settings' => null]);
            }
            if (! in_array($mode, ['public', 'any', 'specific_any'], true)) {
                $user = User::factory()->create();
                $this->grantShopRole($user, $scheduling ? 'owner' : 'manager');
                $token = $user->createToken('race-test', ['admin'])->plainTextToken;
            }
            foreach ($times as $index => $time) {
                $editing = $scheduling && $index === 0;
                $staff = $editing || $mode === 'staff' || ($mode === 'mixed' && $index === 0) || (in_array($mode, ['schedule_staff', 'business_staff'], true) && $index === 1);
                $process = new Process([PHP_BINARY, base_path('tests/Support/booking-worker.php'), $time, $barrier, (string) $index], base_path(), ['BOOKING_STAFF_TOKEN' => $staff ? $token : '', 'BOOKING_SCHEDULE_EDIT' => $editing ? '1' : '', 'BOOKING_BUSINESS_EDIT' => $editing && str_starts_with($mode, 'business_') ? '1' : '', 'BOOKING_ANY_BARBER' => $mode === 'any' || ($mode === 'specific_any' && $index === 1) ? '1' : '']);
                $process->setTimeout(120);
                $process->start();
                $processes[] = $process;
            }
            foreach ($processes as $index => $process) {
                while (! file_exists($barrier.'.ready'.$index) && $process->isRunning()) {
                    $process->checkTimeout();
                    usleep(10000);
                    clearstatcache();
                }
                $this->assertFileExists($barrier.'.ready'.$index, $process->getErrorOutput());
            }
            touch($barrier);
            $statuses = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
                preg_match('/STATUS:(\d+)/', $process->getOutput(), $matches);
                $statuses[] = (int) ($matches[1] ?? 0);
            }
            sort($statuses);
            if ($scheduling) {
                $this->assertContains($statuses, [[200, 422], [201, 422]]);
                $this->assertSame($statuses[0] === 201 ? 1 : 0, DB::table('bookings')->where('booking_date', $date)->count());
            } elseif ($mode === 'any') {
                $this->assertSame([201, 201], $statuses);
                $this->assertSame(2, DB::table('bookings')->where('booking_date', $date)->distinct()->count('barber_id'));
            } elseif ($mode === 'specific_any') {
                $this->assertContains($statuses, [[201, 201], [201, 409]]);
                $bookings = DB::table('bookings')->where('booking_date', $date);
                $this->assertSame($bookings->count(), $bookings->distinct()->count('barber_id'));
            } else {
                $this->assertSame([201, 409], $statuses);
                $this->assertSame(1, DB::table('bookings')->where('booking_date', $date)->count());
            }
        } finally {
            foreach ($processes as $process) {
                $process->stop();
            }
            foreach ([$barrier, $barrier.'.ready0', $barrier.'.ready1'] as $path) {
                if (file_exists($path)) {
                    unlink($path);
                }
            }
            DB::table('bookings')->where('booking_date', $date)->delete();
            DB::table('booking_day_locks')->where('booking_date', $date)->delete();
            DB::table('shop_booking_day_locks')->where('shop_id', 1)->where('booking_date', $date)->delete();
            if ($user) {
                $user->tokens()->delete();
                DB::table('audit_events')->where('user_id', $user->id)->delete();
                $user->delete();
            }
            if ($scheduling) {
                DB::table('shops')->where('id', 1)->update(['settings' => $originalSettings]);
            }
        }
    }
}
