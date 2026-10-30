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
$staffToken = getenv('BOOKING_STAFF_TOKEN');
$headers = ['HTTP_ACCEPT' => 'application/json'];
if ($staffToken) {
    $headers['HTTP_AUTHORIZATION'] = 'Bearer '.$staffToken;
}
$payload = [
    'service_id' => 2,
    'barber_id' => getenv('BOOKING_ANY_BARBER') === '1' ? 'any' : 1,
    'booking_date' => '2099-11-03',
    'booking_time' => $argv[1],
    'customer_name' => 'Concurrency test',
    'email' => 'concurrency@example.com',
    'phone' => '0712345678',
];
$editing = getenv('BOOKING_SCHEDULE_EDIT') === '1';
if ($editing) {
    $payload = ['version' => 0, 'hours' => array_fill(1, 7, ['10:00', '11:00']), 'breaks' => [], 'closed_dates' => []];
}
$editPath = getenv('BOOKING_BUSINESS_EDIT') === '1' ? '/api/admin/management/business-hours' : '/api/admin/management/barbers/1/schedule';
$request = Request::create($editing ? $editPath : ($staffToken ? '/api/admin/bookings' : '/api/bookings'), $editing ? 'PUT' : 'POST', $payload, [], [], $headers);
$response = $kernel->handle($request);
echo 'STATUS:'.$response->getStatusCode()."\n";
$kernel->terminate($request, $response);
