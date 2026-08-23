<?php

return [
    'length' => (int) env('OTP_LENGTH', 6),
    'ttl_seconds' => (int) env('OTP_TTL_SECONDS', 300),
    'max_failures' => (int) env('OTP_MAX_FAILURES', 5),
    'max_emissions' => (int) env('OTP_MAX_EMISSIONS', 3),
    'resend_cooldown_seconds' => (int) env('OTP_RESEND_COOLDOWN_SECONDS', 60),
    'mac_key' => env('OTP_MAC_KEY'),
];
