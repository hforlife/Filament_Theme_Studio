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

    'custom_css' => [
        'enabled' => false,
        'max_bytes' => 50_000,
        'mode' => 'strict',
        'allow_on_auth_pages' => false,
        'allow_external_urls' => false,
        'allowed_media_queries' => true,
    ],
];
