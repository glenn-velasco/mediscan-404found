<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'machine_learning' => [
        'base_url' => env('MACHINE_LEARNING_BASE_URL', 'http://machine-learning:8500'),
        'token' => env('MACHINE_LEARNING_SHARED_SECRET'),
        'timeout' => env('MACHINE_LEARNING_TIMEOUT', 15),
    ],

    'google' => [
        'analytics_id' => env('GOOGLE_ANALYTICS_ID'),
    ],

    'cloud_vision' => [
        'key_base64' => env('GOOGLE_CLOUD_VISION_KEY_BASE64'),
        'project_id' => env('GOOGLE_CLOUD_PROJECT'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mobile App Links / Universal Links
    |--------------------------------------------------------------------------
    |
    | These values are used to serve the /.well-known files that Android and
    | iOS use to verify domain-to-app associations. When configured, clicking
    | a link to app.mediscan.cloud will show an "Open with MediScan" prompt
    | on the user's phone instead of opening the browser.
    |
    | android_sha256: The SHA-256 fingerprint of the signing certificate.
    |   Get it with: eas credentials --platform android
    |
    | ios_team_id: Your Apple Developer Team ID (10 characters).
    |   Find it at https://developer.apple.com/account → Membership
    |
    */

    'app_links' => [
        'android_sha256' => env('ANDROID_SHA256_FINGERPRINT'),
        'ios_team_id' => env('IOS_TEAM_ID'),
    ],

];
