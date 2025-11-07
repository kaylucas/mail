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
    'default' => env('PRISM_PROVIDER', 'anthropic'),

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
            'model' => 'claude-3-5-sonnet-latest',
            'temperature' => 0.7,
            'max_tokens' => 2000,
            'timeout' => 30,
            'retry' => [
                'times' => 3,
                'sleep' => 1000,
            ],
        ],

        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => 'gpt-4o',
            'temperature' => 0.7,
            'max_tokens' => 2000,
            'timeout' => 30,
            'retry' => [
                'times' => 3,
                'sleep' => 1000,
            ],
        ],

        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'model' => 'gemini-pro',
            'temperature' => 0.7,
            'max_tokens' => 2000,
            'timeout' => 30,
            'retry' => [
                'times' => 3,
                'sleep' => 1000,
            ],
        ],
    ],
];
