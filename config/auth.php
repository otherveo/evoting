<?php

use App\Models\StaffAccount;

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'staff'),
        'passwords' => 'staff',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'staff',
        ],
        'staff' => [
            'driver' => 'session',
            'provider' => 'staff',
        ],
    ],

    'providers' => [
        'staff' => [
            'driver' => 'eloquent',
            'model' => StaffAccount::class,
        ],
    ],

    'passwords' => [
        'staff' => [
            'provider' => 'staff',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
