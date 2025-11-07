<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Email Rules Feature Toggle
    |--------------------------------------------------------------------------
    |
    | This option controls whether the email rules feature is enabled.
    | Set to false to completely disable rule processing.
    |
    */
    'enabled' => env('EMAIL_RULES_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Rules Processing Queue
    |--------------------------------------------------------------------------
    |
    | This option controls which queue email rule jobs will be dispatched to.
    | You can create a dedicated queue for email rules to manage priority.
    |
    */
    'queue' => env('EMAIL_RULES_QUEUE', 'email-rules'),

    /*
    |--------------------------------------------------------------------------
    | Daily Reminder Check Time
    |--------------------------------------------------------------------------
    |
    | The time when the system will check for due email reminders.
    | Format: HH:MM (24-hour format)
    |
    */
    'reminder_check_time' => env('EMAIL_REMINDER_CHECK_TIME', '09:00'),

    /*
    |--------------------------------------------------------------------------
    | Maximum Rules Per User
    |--------------------------------------------------------------------------
    |
    | The maximum number of rules that a single user can create.
    | This helps prevent abuse and maintain system performance.
    |
    */
    'max_rules_per_user' => 50,

    /*
    |--------------------------------------------------------------------------
    | Maximum Actions Per Rule
    |--------------------------------------------------------------------------
    |
    | The maximum number of actions that can be configured for a single rule.
    |
    */
    'max_actions_per_rule' => 10,

    /*
    |--------------------------------------------------------------------------
    | Rule Evaluation Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum time in seconds for evaluating a single rule against an email.
    | This includes AI processing time.
    |
    */
    'evaluation_timeout' => 30,

    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    |
    | The default AI provider to use when a rule doesn't specify one.
    | Options: anthropic, openai, gemini
    |
    */
    'default_provider' => env('PRISM_PROVIDER', 'anthropic'),

    /*
    |--------------------------------------------------------------------------
    | Skip Update Fields
    |--------------------------------------------------------------------------
    |
    | Email field changes that should not trigger rule processing.
    | These are typically user actions that don't change the email content.
    |
    */
    'skip_update_fields' => [
        'is_read',
        'flag_status',
        'updated_at',
    ],
];
