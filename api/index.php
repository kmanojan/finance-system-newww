<?php

// Prepare writable directories in /tmp for Vercel serverless environment
$directories = [
    '/tmp/storage/app',
    '/tmp/storage/framework/cache',
    '/tmp/storage/framework/sessions',
    '/tmp/storage/framework/views',
    '/tmp/storage/logs',
];

foreach ($directories as $directory) {
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
}

// Forward execution to Laravel entry point
require __DIR__ . '/../public/index.php';
