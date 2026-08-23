<?php

return [
    'client_initial_password' => env('CLIENT_INITIAL_PASSWORD', '1234*'),
    'session' => [
        'idle_timeout_minutes' => (int) env('SESSION_IDLE_TIMEOUT_MINUTES', 30),
        'absolute_lifetime_minutes' => (int) env('SESSION_ABSOLUTE_LIFETIME_MINUTES', 480),
    ],
    'rate_limits' => [
        'account_failures' => (int) env('LOGIN_ACCOUNT_MAX_FAILURES', 5),
        'account_decay_seconds' => (int) env('LOGIN_ACCOUNT_DECAY_SECONDS', 900),
        'ip_failures' => (int) env('LOGIN_IP_MAX_FAILURES', 20),
        'ip_decay_seconds' => (int) env('LOGIN_IP_DECAY_SECONDS', 900),
    ],
];
