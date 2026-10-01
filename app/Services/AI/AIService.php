<?php

namespace App\Services\AI;

use App\Services\AI\Contracts\AIProviderInterface;
use App\Services\AI\DTOs\AIRequest;
use App\Services\AI\DTOs\AIResponse;
use App\Services\AI\DTOs\AIMessage;
use App\Services\AI\Exceptions\AIInvalidResponseException;

class AIService
{
    private ?AIResponse $lastResponse = null;

    public function __construct(
        private readonly AIProviderInterface $provider
    ) {}

    /**
     * إرسال طلب عادي إلى الـ AI.
     */
    public function chat(
        string $userPrompt,
        ?string $systemPrompt = null,
        ?string $purpose = null,
        float $temperature = 0.3,
        ?string $model = null,
    ): AIResponse {
        $request = $this->buildRequest(
            userPrompt: $userPrompt,
            systemPrompt: $systemPrompt,
            purpose: $purpose,
            temperature: $temperature,
            model: $model,
        );

        $this->lastResponse = $this->provider->chat($request);

        return $this->lastResponse;
    }

    /**
     * إرسال طلب إلى الـ AI مع إجبار الاستجابة على JSON.
     *
     * هذه الدالة ترجع AIResponse كاملًا حتى نستطيع
     * الوصول إلى json + model + tokens + latency.
     */
    public function chatJsonResponse(
        string $userPrompt,
        array $schema,
        ?string $systemPrompt = null,
        ?string $purpose = null,
        float $temperature = 0.2,
        ?string $model = null,
    ): AIResponse {
        if (!str_contains(strtolower($userPrompt), 'json')) {
            $userPrompt .= "\n\nأعد الإجابة بصيغة JSON فقط، بدون أي نص إضافي.";
        }

        $request = $this->buildRequest(
            userPrompt: $userPrompt,
            systemPrompt: $systemPrompt,
            purpose: $purpose,
            temperature: $temperature,
            model: $model,
        )->asJson();

        $this->lastResponse = $this->provider->chat($request);

        if ($this->lastResponse->json === null) {
            throw AIInvalidResponseException::invalidJson(
                $this->lastResponse->content
            );
        }

        return $this->lastResponse;
    }

    /**
     * إرسال طلب JSON وإرجاع البيانات فقط.
     */
    public function chatJson(
        string $userPrompt,
        array $schema,
        ?string $systemPrompt = null,
        ?string $purpose = null,
        float $temperature = 0.2,
        ?string $model = null,
    ): array {
        $response = $this->chatJsonResponse(
            userPrompt: $userPrompt,
            schema: $schema,
            systemPrompt: $systemPrompt,
            purpose: $purpose,
            temperature: $temperature,
            model: $model,
        );

        return $response->json;
    }

    /**
     * آخر رد من الـ AI.
     */
    public function lastResponse(): ?AIResponse
    {
        return $this->lastResponse;
    }

    // ==========================================================
    // Private
    // ==========================================================

    private function buildRequest(
        string $userPrompt,
        ?string $systemPrompt,
        ?string $purpose,
        float $temperature,
        ?string $model,
    ): AIRequest {
        $messages = [];

        if ($systemPrompt) {
            $messages[] = AIMessage::system($systemPrompt);
        }

        $messages[] = AIMessage::user($userPrompt);

        $request = AIRequest::make($messages)
            ->withTemperature($temperature)
            ->forPurpose($purpose ?? 'general');

        if ($model) {
            $request = $request->withModel($model);
        }

        return $request;
    }
}