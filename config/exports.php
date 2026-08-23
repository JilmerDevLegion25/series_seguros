<?php

return [
    'disk' => env('EXPORT_DISK', 'exports'),
    'max_rows' => (int) env('EXPORT_MAX_ROWS', 5000),
];
