<?php

return [
    'default' => env('FILESYSTEM_DISK', 'local'),
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => false,
            'throw' => true,
        ],
        'imports' => [
            'driver' => 'local',
            'root' => storage_path('app/private/imports'),
            'serve' => false,
            'throw' => true,
        ],
        'exports' => [
            'driver' => 'local',
            'root' => storage_path('app/private/exports'),
            'serve' => false,
            'throw' => true,
        ],
    ],
    'links' => [],
];
