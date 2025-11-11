<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Prism Provider
    |--------------------------------------------------------------------------
    |
    | This option controls the default AI provider that will be used by Prism
    | when no specific provider is selected. You can choose from: anthropic,
    | openai, or gemini.
    |
    */
    'default' => env('PRISM_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure the settings for each AI provider. Each provider
    | requires an API key and has its own set of models and parameters.
    |
    */
    'providers' => [
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'version' => env('ANTHROPIC_API_VERSION', '2023-06-01'),
            'url' => env('ANTHROPIC_URL', 'https://api.anthropic.com'),
            'model' => env('ANTHROPIC_MODEL', 'claude-3-5-sonnet-latest'),
            'temperature' => env('ANTHROPIC_TEMPERATURE', 0.7),
            'max_tokens' => env('ANTHROPIC_MAX_TOKENS', 2000),
            'timeout' => env('ANTHROPIC_TIMEOUT', 30),
            'retry' => [
                'times' => 3,
                'sleep' => 1000,
            ],
        ],

        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'url' => env('OPENAI_URL', 'https://api.openai.com/v1'),
            'organization' => env('OPENAI_ORGANIZATION', null),
            'project' => env('OPENAI_PROJECT', null),
            'model' => env('OPENAI_MODEL', 'gpt-4o'),
            'temperature' => env('OPENAI_TEMPERATURE', 0.7),
            'max_tokens' => env('OPENAI_MAX_TOKENS', 2000),
            'timeout' => env('OPENAI_TIMEOUT', 30),
            'retry' => [
                'times' => 3,
                'sleep' => 1000,
            ],
        ],

        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'url' => env('GEMINI_URL', 'https://generativelanguage.googleapis.com/v1beta/models'),
            'model' => env('GEMINI_MODEL', 'gemini-pro'),
            'temperature' => env('GEMINI_TEMPERATURE', 0.7),
            'max_tokens' => env('GEMINI_MAX_TOKENS', 2000),
            'timeout' => env('GEMINI_TIMEOUT', 30),
            'retry' => [
                'times' => 3,
                'sleep' => 1000,
            ],
        ],
    ],
];
