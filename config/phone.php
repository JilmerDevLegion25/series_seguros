<?php

$allowedCountryCodes = array_values(array_filter(array_map(
    static fn (string $code): string => trim($code),
    explode(',', (string) env('PHONE_ALLOWED_COUNTRY_CODES', '57')),
)));

return [
    'allowed_country_codes' => $allowedCountryCodes === [] ? ['57'] : $allowedCountryCodes,
];
