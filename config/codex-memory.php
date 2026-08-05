<?php

$homeDirectory = $_SERVER['HOME'] ?? $_ENV['HOME'] ?? getenv('HOME');

if ((! is_string($homeDirectory) || $homeDirectory === '') && function_exists('posix_getpwuid')) {
    $homeDirectory = posix_getpwuid(posix_geteuid())['dir'] ?? '';
}

$homeDirectory = is_string($homeDirectory) ? $homeDirectory : '';
$defaultRoot = $homeDirectory !== '' ? rtrim($homeDirectory, '/').'/knowledge/work-project' : '';
$defaultEngineeringRoot = $homeDirectory !== '' ? rtrim($homeDirectory, '/').'/Code' : '';

return [
    'root' => env('PROJECT_MEMORY_ROOT', $defaultRoot),
    'engineering_roots' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('PROJECT_ENGINEERING_ROOTS', $defaultEngineeringRoot)),
    ))),
    'auto_sync_interval_minutes' => max(
        1,
        (int) env('PROJECT_MEMORY_SYNC_INTERVAL', 10),
    ),
];
