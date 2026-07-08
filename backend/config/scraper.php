<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Scraper Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the masothue.com scraping service.
    | Proxy settings differ between dev and production environments.
    |
    */

    // Base URL of the data source
    'base_url' => env('SCRAPER_BASE_URL', 'https://masothue.com'),

    // Rotating proxy endpoint (format: http://user:pass@host:port)
    // In production, this should point to your rotating proxy service
    'proxy_endpoint' => env('SCRAPER_PROXY_ENDPOINT'),

    // Static proxy list (used if proxy_endpoint is null)
    // Comma-separated in env: http://proxy1:port,http://proxy2:port
    'proxy_list' => env('SCRAPER_PROXY_LIST')
        ? explode(',', env('SCRAPER_PROXY_LIST'))
        : [],

    // TMProxy API Key (if using tmproxy.com)
    'tmproxy_key' => env('SCRAPER_TMPROXY_KEY'),

    // Google Sheets integration
    'google_credentials_path' => env('GOOGLE_CREDENTIALS_PATH', base_path('google-credentials.json')),
    'google_drive_folder_id' => env('GOOGLE_DRIVE_FOLDER_ID', '10O6NWxq6s92Kb63YpqMb6x04gmAwW6y9'),

    // Whether to verify SSL certificates through proxy
    'verify_ssl' => env('SCRAPER_VERIFY_SSL', true),

    // Maximum companies to process per scrape run
    'max_per_run' => env('SCRAPER_MAX_PER_RUN', 50),

    // Delay between individual company requests (milliseconds)
    'request_delay_min' => env('SCRAPER_DELAY_MIN', 200),
    'request_delay_max' => env('SCRAPER_DELAY_MAX', 500),

    // Schedule interval in minutes
    'schedule_interval' => env('SCRAPER_INTERVAL_MINUTES', 2),
];
