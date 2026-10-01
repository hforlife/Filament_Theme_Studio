<?php

return [
    'enabled' => true,

    'cache' => [
        'enabled' => true,
        'store' => null,
        'ttl' => 3600,
        'prefix' => 'filament-theme-studio',
    ],

    'versions' => [
        'limit' => 20,
    ],

    'css' => [
        'enabled' => true,
        'route_prefix' => 'filament-theme-studio/styles',
        'cache_control' => 'public, max-age=3600',
        'compiler_version' => '1',
    ],
];
