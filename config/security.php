<?php

return [
    'headers' => [
        'content_security_policy' => env(
            'SECURITY_CSP',
            "default-src 'self'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'; object-src 'none'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'"
        ),
        'hsts' => [
            'enabled' => (bool) env('SECURITY_HSTS_ENABLED', false),
            'value' => 'max-age=31536000; includeSubDomains',
        ],
        'permissions_policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        'referrer_policy' => 'same-origin',
    ],
];
