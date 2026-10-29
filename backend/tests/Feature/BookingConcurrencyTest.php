<?php

namespace Tests\Feature;

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
            'overlapping starts' => [['10:00', '10:20']],
        ];
    }

    #[DataProvider('competingTimes')]
    public function test_competing_requests_for_an_empty_day_create_only_one_booking(array $times): void
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
        $barrier = tempnam(sys_get_temp_dir(), 'booking-race-');
        unlink($barrier);
        try {
            foreach ($times as $index => $time) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/booking-worker.php'), $time, $barrier, (string) $index], base_path());
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
            $this->assertSame([201, 409], $statuses);
            $this->assertSame(1, DB::table('bookings')->where('booking_date', $date)->count());
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
        }
    }
}
