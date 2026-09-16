<?php

return [
    'openrouter' => [
        'api_key'  => env('GROQ_API_KEY'),
        'base_url' => env('AI_BASE_URL', 'https://api.groq.com/openai/v1'),
        'model'    => env('AI_MODEL', 'llama-3.3-70b-versatile'),
        'site_url' => env('OPENROUTER_SITE_URL', config('app.url')),
        'site_name'=> env('OPENROUTER_SITE_NAME', config('app.name')),
        'timeout'  => 60,
    ],
];