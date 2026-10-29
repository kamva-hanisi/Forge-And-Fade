<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
touch($argv[2].'.ready'.$argv[3]);
$deadline = microtime(true) + 110;
while (! file_exists($argv[2])) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Timed out waiting for the concurrency barrier.');
    }
    usleep(10000);
    clearstatcache();
}
$request = Request::create('/api/bookings', 'POST', [
    'service_id' => 2,
    'barber_id' => 1,
    'booking_date' => '2099-11-03',
    'booking_time' => $argv[1],
    'customer_name' => 'Concurrency test',
    'email' => 'concurrency@example.com',
    'phone' => '0712345678',
], [], [], ['HTTP_ACCEPT' => 'application/json']);
$response = $kernel->handle($request);
echo 'STATUS:'.$response->getStatusCode()."\n";
$kernel->terminate($request, $response);
