<?php

return [
    'booking_per_minute' => max(1, (int) env('BOOKING_REQUESTS_PER_MINUTE', 15)),
    'booking_per_hour' => max(1, (int) env('BOOKING_REQUESTS_PER_HOUR', 30)),
    'contact_per_minute' => max(1, (int) env('CONTACT_REQUESTS_PER_MINUTE', 5)),
    'contact_per_hour' => max(1, (int) env('CONTACT_REQUESTS_PER_HOUR', 20)),
    'availability_per_minute' => max(1, (int) env('AVAILABILITY_REQUESTS_PER_MINUTE', 120)),
];
