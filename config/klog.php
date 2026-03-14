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

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    |
    | Configuration for chunked file uploads. chunk_size is in bytes (default
    | 2 MB). max_file_size env var is in megabytes (default 500 MB).
    | session_ttl is in hours — incomplete sessions older than this are
    | cleaned up automatically.
    |
    */

    'uploads' => [
        'chunk_size' => (int) env('UPLOAD_CHUNK_SIZE', 2 * 1024 * 1024),
        'max_file_size' => (int) env('UPLOAD_MAX_FILE_SIZE', 500) * 1024 * 1024,
        'session_ttl' => (int) env('UPLOAD_SESSION_TTL', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Media Optimization
    |--------------------------------------------------------------------------
    |
    | Server-side media optimization settings. HEIC/HEIF/AVIF images are
    | converted to JPEG. MOV/WebM videos are re-encoded to H.264 MP4.
    | These run as queued jobs after upload.
    |
    */

    'media_optimization' => [
        'image_max_dimension' => (int) env('IMAGE_MAX_DIMENSION', 2048),
        'image_quality' => (int) env('IMAGE_QUALITY', 85),
        'video_max_dimension' => (int) env('VIDEO_MAX_DIMENSION', 2048),
        'video_crf' => (int) env('VIDEO_CRF', 23),
        'video_preset' => env('VIDEO_PRESET', 'veryfast'),
        'ffmpeg_path' => env('FFMPEG_PATH', 'ffmpeg'),
        'ffprobe_path' => env('FFPROBE_PATH', 'ffprobe'),
    ],

    'two_factor' => [
        'email_code_ttl' => 10,
        'remember_days' => (int) env('TWO_FACTOR_REMEMBER_DAYS', 30),
        'recovery_code_count' => 8,
        'max_attempts' => 5,
        'decay_minutes' => 10,
    ],

];
