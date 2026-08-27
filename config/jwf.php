<?php

declare(strict_types=1);

return [
    'disk' => env('JWF_FILESYSTEM_DISK', 'local'),
    'directory' => env('JWF_FILESYSTEM_DIRECTORY', 'jwf'),
    'files' => [
        'max_size_kb' => 10240,
        'allowed_mime_types' => [],
        'allowed_extensions' => [],
    ],
];
