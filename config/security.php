<?php

return [
    'origin_mode' => env('SECURITY_ORIGIN_MODE', 'enforce'),
    'whitelist_mode' => env('SECURITY_WHITELIST_MODE', 'enforce'),
    'csp_mode' => env('SECURITY_CSP_MODE', 'report'),
    'hsts_max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 86400),
    'cloudflare_ranges' => array_values(array_filter(array_map('trim', explode(',', (string) env('SECURITY_CLOUDFLARE_RANGES', ''))))),
];
