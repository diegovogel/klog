<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maintainer Email
    |--------------------------------------------------------------------------
    |
    | The email address that receives error notifications. If not set, Klog
    | will attempt to email registered users in order until one succeeds,
    | then remember that recipient for future errors.
    |
    */

    'maintainer_email' => env('MAINTAINER_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | Two-Factor Authentication
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | Screenshots
    |--------------------------------------------------------------------------
    |
    | Configuration for the optional web clipping screenshot feature.
    | no_sandbox: Chromium's --no-sandbox flag is required when running as
    | root or in some containerized environments. Set to false if your
    | environment supports sandboxing for better security isolation.
    |
    */

    'screenshots' => [
        'no_sandbox' => (bool) env('SCREENSHOTS_NO_SANDBOX', true),
    ],

    'two_factor' => [
        'email_code_ttl' => 10,
        'remember_days' => (int) env('TWO_FACTOR_REMEMBER_DAYS', 30),
        'recovery_code_count' => 8,
        'max_attempts' => 5,
        'decay_minutes' => 10,
    ],

];
