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

    'two_factor' => [
        'email_code_ttl' => 10,
        'remember_days' => (int) env('TWO_FACTOR_REMEMBER_DAYS', 30),
        'recovery_code_count' => 8,
        'max_attempts' => 5,
        'decay_minutes' => 10,
    ],

];
