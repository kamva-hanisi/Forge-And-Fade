<?php

return [
    'sandbox_merchants' => json_decode((string) env('PAYFAST_SANDBOX_MERCHANTS', '{}'), true) ?: [],
    'callback_base_url' => env('PAYFAST_CALLBACK_BASE_URL', 'http://127.0.0.1:8000'),
    'notify_url' => env('PAYFAST_NOTIFY_URL'),
];
