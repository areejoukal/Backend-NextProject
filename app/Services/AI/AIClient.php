<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\DTOs\AIRequest;
use App\Services\AI\DTOs\AIResponse;
use App\Services\AI\Exceptions\AIException;
use App\Services\AI\Exceptions\AIInvalidResponseException;
use App\Services\AI\Exceptions\AIRateLimitException;
use App\Services\AI\Exceptions\AITimeoutException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIClient implements AIProviderInterface
{
    private string $provider;
    private string $apiKey;
    private string $baseUrl;
    private string $defaultModel;
    private string $fallbackModel;
    private int $timeout;
    private int $maxRetries;
    private array $extraHeaders = [];

    public function __construct(?string $provider = null)
    {
        $this->provider = $provider ?? config('ai.provider', 'groq');

        $config = config("ai.providers.{$this->provider}");

        if (!$config) {
            throw new \RuntimeException(
                "AI provider '{$this->provider}' is not configured."
            );
        }

        if (empty($config['api_key'])) {
            throw new \RuntimeException(
                "API key for '{$this->provider}' is missing. " .
                "Please set the appropriate API key in .env"
            );
        }

        $this->apiKey        = $config['api_key'];
        $this->baseUrl       = rtrim($config['base_url'], '/');
        $this->defaultModel  = $config['model'];
        $this->fallbackModel = $config['fallback_model'] ?? $config['model'];
        $this->timeout       = (int) ($config['timeout'] ?? 60);
        $this->maxRetries    = (int) ($config['max_retries'] ?? 2);

        // OpenRouter يحتاج headers إضافية
        if ($this->provider === 'openrouter') {
            $this->extraHeaders = [
                'HTTP-Referer' => $config['site_url'] ?? config('app.url'),
                'X-Title'      => $config['site_name'] ?? config('app.name'),
            ];
        }
    }

    // ==========================================================
    // Public API
    // ==========================================================

    public function chat(AIRequest $request): AIResponse
    {
        $model = $request->model ?? $this->defaultModel;
        $startTime = microtime(true);

        try {
            $response = $this->sendRequest($request, $model);
        } catch (AIException $e) {
            if ($model !== $this->fallbackModel) {
                Log::warning('Primary model failed, using fallback', [
                    'provider' => $this->provider,
                    'primary'  => $model,
                    'fallback' => $this->fallbackModel,
                    'error'    => $e->getMessage(),
                ]);

                $response = $this->sendRequest($request, $this->fallbackModel);
                $model = $this->fallbackModel;
            } else {
                throw $e;
            }
        }

        $latencyMs = (int) ((microtime(true) - $startTime) * 1000);

        return $this->buildResponse($response, $latencyMs, $request);
    }

    public function chatJson(AIRequest $request, array $schema): array
    {
        $request = $request->asJson();
        $response = $this->chat($request);

        if ($response->json === null) {
            throw AIInvalidResponseException::invalidJson($response->content);
        }

        return $response->json;
    }

    // ==========================================================
    // Private
    // ==========================================================

    private function sendRequest(AIRequest $request, string $model): Response
    {
        $payload = $this->buildPayload($request, $model);

        $this->logRequest($request, $model);

        try {
            $response = Http::withHeaders($this->headers())
                ->timeout($this->timeout)
                ->retry(
                    $this->maxRetries,
                    1000,
                    function ($exception) {
                        return $exception instanceof ConnectionException;
                    },
                    throw: false
                )
                ->post("{$this->baseUrl}/chat/completions", $payload);

        } catch (ConnectionException $e) {
            throw AITimeoutException::afterSeconds($this->timeout);
        }

        $this->handleErrorResponse($response);

        return $response;
    }

    private function buildPayload(AIRequest $request, string $model): array
    {
        $payload = [
            'model'       => $model,
            'messages'    => array_map(fn ($m) => $m->toArray(), $request->messages),
            'temperature' => $request->temperature,
        ];

        if ($request->maxTokens) {
            $payload['max_tokens'] = $request->maxTokens;
        }

        if ($request->jsonMode) {
            // Groq & OpenAI يدعمان json_object
            $payload['response_format'] = ['type' => 'json_object'];
        }

        return $payload;
    }

    private function headers(): array
    {
        return array_merge([
            'Authorization' => "Bearer {$this->apiKey}",
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ], $this->extraHeaders);
    }

    private function handleErrorResponse(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $status = $response->status();
        $body = $response->json() ?? [];

        if ($status === 429) {
            $retryAfter = (int) ($response->header('Retry-After') ?? 60);
            throw AIRateLimitException::retryAfter($retryAfter);
        }

        if ($status === 408 || $status === 504) {
            throw AITimeoutException::afterSeconds($this->timeout);
        }

        $message = $body['error']['message']
            ?? $body['message']
            ?? "{$this->provider} error (HTTP {$status})";

        throw new AIException(
            $message,
            $status,
            context: ['response' => $body, 'provider' => $this->provider]
        );
    }

    private function buildResponse(
        Response $response,
        int $latencyMs,
        AIRequest $request
    ): AIResponse {
        $data = $response->json();

        if (empty($data['choices'][0]['message']['content'])) {
            throw AIInvalidResponseException::missingChoices();
        }

        $content = $data['choices'][0]['message']['content'];
        $usage = $data['usage'] ?? [];
        $model = $data['model'] ?? 'unknown';

        $json = null;
        if ($request->jsonMode) {
            $json = $this->tryParseJson($content);
        }

        $aiResponse = new AIResponse(
            content: $content,
            json: $json,
            model: $model,
            promptTokens: $usage['prompt_tokens'] ?? 0,
            completionTokens: $usage['completion_tokens'] ?? 0,
            totalTokens: $usage['total_tokens'] ?? 0,
            latencyMs: $latencyMs,
            raw: $data,
        );

        $this->logResponse($aiResponse, $request);

        return $aiResponse;
    }

    private function tryParseJson(string $content): ?array
    {
        // محاولة مباشرة
        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // استخراج من markdown code block
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $content, $matches)) {
            $decoded = json_decode($matches[1], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        // استخراج أول { ... }
        if (preg_match('/(\{.*\})/s', $content, $matches)) {
            $decoded = json_decode($matches[1], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return null;
    }

    private function logRequest(AIRequest $request, string $model): void
    {
        Log::info('AI request sent', [
            'provider'       => $this->provider,
            'purpose'        => $request->purpose ?? 'general',
            'model'          => $model,
            'messages_count' => count($request->messages),
            'json_mode'      => $request->jsonMode,
            'temperature'    => $request->temperature,
        ]);
    }

    private function logResponse(AIResponse $response, AIRequest $request): void
    {
        Log::info('AI response received', [
            'provider'   => $this->provider,
            'purpose'    => $request->purpose ?? 'general',
            'model'      => $response->model,
            'tokens'     => $response->totalTokens,
            'latency_ms' => $response->latencyMs,
        ]);
    }
}