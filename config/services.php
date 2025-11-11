<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'office365' => [
        'tenant_id' => env('OFFICE365_TENANT_ID', 'common'),
        'client_id' => env('OFFICE365_CLIENT_ID'),
        'client_secret' => env('OFFICE365_CLIENT_SECRET'),
        'redirect_uri' => env('OFFICE365_REDIRECT_URI'),
        'scopes' => env('OFFICE365_SCOPES', 'offline_access,Mail.Read'),
    ],

    'microsoft_graph' => [
        // Webhook base URL - must be publicly accessible HTTPS endpoint
        // Development: ngrok tunnel URL (e.g., https://aery.eu.ngrok.io)
        // Production: Your application's public domain
        'webhook_base_url' => env('WEBHOOK_BASE_URL', env('APP_URL')),

        // Secret key for generating clientState values
        // Used to validate webhook notifications are from Microsoft
        // Generate with: php artisan tinker -> Str::random(32)
        'webhook_secret' => env('WEBHOOK_SECRET_KEY'),

        // Subscription expiration time in minutes
        // Maximum for mail resources: 10,070 minutes (Microsoft's actual limit, not 10,080)
        // Minimum: 45 minutes (auto-bumped by Microsoft if lower)
        // Default: ~7 days (10,070 minutes) - renew earlier operationally
        // Note: Documentation says 10,080 but API rejects above 10,070
        'subscription_expiration_minutes' => (int) env('GRAPH_SUBSCRIPTION_EXPIRATION_MINUTES', 10070),

        // Renewal threshold in hours
        // Renew subscriptions when they expire within this threshold
        // Recommended: 12 hours to ensure subscriptions never expire
        'subscription_renewal_threshold_hours' => env('GRAPH_SUBSCRIPTION_RENEWAL_THRESHOLD_HOURS', 12),

        // Notification URL paths (relative to webhook_base_url)
        // notification_url_path handles both GET (validation) and POST (notifications)
        'notification_url_path' => '/webhooks/microsoft/notifications',
        'lifecycle_url_path' => '/webhooks/microsoft/lifecycle',
    ],

];
