<?php

$renderUrl = rtrim((string) env('RENDER_EXTERNAL_URL', ''), '/');

return [
    'sandbox_enabled' => (bool) env('PAYFAST_SANDBOX_ENABLED', false),
    'sandbox_merchants' => json_decode((string) env('PAYFAST_SANDBOX_MERCHANTS', '{}'), true) ?: [],
    'callback_base_url' => env('PAYFAST_CALLBACK_BASE_URL') ?: ($renderUrl ?: 'http://127.0.0.1:8000'),
    'notify_url' => env('PAYFAST_NOTIFY_URL') ?: ($renderUrl ? $renderUrl.'/api/payfast/notify' : null),
    'render_ingress' => (bool) env('RENDER', false) && $renderUrl !== '',
];
