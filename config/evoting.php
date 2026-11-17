<?php

return [
    'display_timezone' => env('APP_TIMEZONE', 'Asia/Makassar'),
    'browser_cookie_name' => 'evoting_browser_id',
    'browser_cookie_days' => 30,
    'voting_session_minutes' => 10,
    'operator_poll_seconds' => 3,
    'min_password_length' => 8,
    'login_max_attempts' => 5,
    'login_decay_seconds' => 60,
    'supabase' => [
        'url' => rtrim((string) env('SUPABASE_URL', ''), '/'),
        'secret_key' => (string) env('SUPABASE_SECRET_KEY', ''),
        'publishable_key' => (string) env('SUPABASE_PUBLISHABLE_KEY', ''),
        'bucket' => (string) env('SUPABASE_STORAGE_BUCKET', 'paslon-foto'),
    ],
];
