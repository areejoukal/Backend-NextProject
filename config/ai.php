<?php

return [

    'provider' => env('AI_PROVIDER', 'groq'),

    'providers' => [

        'groq' => [
            'api_key'        => env('GROQ_API_KEY'),
            'base_url'       => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
            'model'          => env('GROQ_MODEL', 'openai/gpt-oss-120b'),
            'fallback_model' => env('GROQ_FALLBACK_MODEL', 'openai/gpt-oss-20b'),
            'timeout'        => (int) env('GROQ_TIMEOUT', 60),
            'max_retries'    => (int) env('GROQ_MAX_RETRIES', 2),
        ],

        'openrouter' => [
            'api_key'        => env('OPENROUTER_API_KEY'),
            'base_url'       => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
            'model'          => env('OPENROUTER_MODEL', 'openai/gpt-4o-mini'),
            'fallback_model' => env('OPENROUTER_FALLBACK_MODEL', 'openai/gpt-4o-mini'),
            'site_url'       => env('OPENROUTER_SITE_URL', env('APP_URL')),
            'site_name'      => env('OPENROUTER_SITE_NAME', env('APP_NAME')),
            'timeout'        => (int) env('OPENROUTER_TIMEOUT', 60),
            'max_retries'    => (int) env('OPENROUTER_MAX_RETRIES', 2),
        ],

        'openai' => [
            'api_key'        => env('OPENAI_API_KEY'),
            'base_url'       => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'model'          => env('OPENAI_MODEL', 'gpt-4o-mini'),
            'fallback_model' => env('OPENAI_FALLBACK_MODEL', 'gpt-4o-mini'),
            'timeout'        => (int) env('OPENAI_TIMEOUT', 60),
            'max_retries'    => (int) env('OPENAI_MAX_RETRIES', 2),
        ],

    ],

];