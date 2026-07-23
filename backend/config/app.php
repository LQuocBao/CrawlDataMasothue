<?php

return [
    'name' => env('APP_NAME', 'MaSoThue Scraper'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => 'Asia/Ho_Chi_Minh',
    'locale' => 'vi',
    'fallback_locale' => 'en',
    'faker_locale' => 'vi_VN',
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'extension_secret' => env('EXTENSION_SECRET', ''),
    'maintenance' => [
        'driver' => 'file',
    ],
];
