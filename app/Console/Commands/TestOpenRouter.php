<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestOpenRouter extends Command
{
    protected $signature = 'test:openrouter';
    protected $description = 'Test AI connection (Groq)';

    public function handle()
    {
        $this->info('Testing AI connection...');

        $apiKey = config('ai.openrouter.api_key');

        if (empty($apiKey)) {
            $this->error('API key is empty. Check GROQ_API_KEY in .env');
            return 1;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $apiKey,
        ])->timeout(config('ai.openrouter.timeout', 60))
          ->post(config('ai.openrouter.base_url') . '/chat/completions', [
            'model' => config('ai.openrouter.model'),
            'messages' => [
                ['role' => 'user', 'content' => 'قل "مرحبا" فقط.'],
            ],
            'max_tokens' => 2048,
        ]);

        if ($response->failed()) {
            $this->error('Failed: ' . $response->body());
            return 1;
        }

        $data = $response->json();
        $this->info('Response: ' . $data['choices'][0]['message']['content']);
        $this->info('Model: ' . $data['model']);
        $this->info('Tokens: ' . $data['usage']['total_tokens']);
        return 0;
    }
}