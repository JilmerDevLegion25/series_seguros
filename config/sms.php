<?php

return [
    'driver' => env('SMS_DRIVER', 'fake'),
    'allowed_drivers' => ['fake', 'provider'],
    'connect_timeout_seconds' => (int) env('SMS_CONNECT_TIMEOUT_SECONDS', 3),
    'total_timeout_seconds' => (int) env('SMS_TOTAL_TIMEOUT_SECONDS', 10),
    'provider' => [
        'endpoint' => env('SMS_PROVIDER_ENDPOINT'),
        'authorization' => env('SMS_PROVIDER_AUTHORIZATION'),
        'from' => env('SMS_PROVIDER_FROM', 'InfoSMS'),
    ],
    'radicado_retry' => [
        'max_attempts' => (int) env('SMS_RADICADO_RETRY_MAX', 3),
        'window_hours' => (int) env('SMS_RADICADO_RETRY_WINDOW_HOURS', 24),
        'cooldown_seconds' => (int) env('SMS_RADICADO_RETRY_COOLDOWN_SECONDS', 300),
    ],
];
