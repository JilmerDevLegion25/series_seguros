<?php

return [
    'disk' => env('IMPORT_DISK', 'imports'),
    'max_bytes' => (int) env('IMPORT_MAX_BYTES', 5 * 1024 * 1024),
    'max_rows' => (int) env('IMPORT_MAX_ROWS', 5000),
];
