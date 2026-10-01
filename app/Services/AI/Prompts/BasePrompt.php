<?php

namespace App\Services\AI\Prompts;

use App\Services\AI\AIService;
use App\Services\AI\DTOs\AIRequest;
use App\Services\AI\DTOs\AIMessage;
use App\Services\AI\Prompts\Contracts\PromptInterface;

abstract class BasePrompt implements PromptInterface
{
    protected AIService $ai;

    public function __construct(AIService $ai)
    {
        $this->ai = $ai;
    }

    /**
     * تنفيذ الـ prompt وإرجاع النتيجة.
     */
    public function generate(): PromptResult
    {
        $request = $this->build();

        $systemPrompt = $this->extractSystemPrompt($request);
        $userPrompt = $this->extractUserPrompt($request);

        if ($this->schema() !== null) {
            // JSON mode
            $json = $this->ai->chatJson(
                userPrompt: $userPrompt,
                schema: $this->schema(),
                systemPrompt: $systemPrompt,
                purpose: $this->name(),
                temperature: $this->temperature(),
                model: $this->recommendedModel(),
            );

            $this->validate($json);

            return new PromptResult(
                data: $json,
                raw: $this->ai->lastResponse(),
            );
        }

        // Text mode
        $response = $this->ai->chat(
            userPrompt: $userPrompt,
            systemPrompt: $systemPrompt,
            purpose: $this->name(),
            temperature: $this->temperature(),
            model: $this->recommendedModel(),
        );

        return new PromptResult(
            data: $response->content,
            raw: $response,
        );
    }

    /**
     * التحقق من صحة JSON المُستلم. الأبناء يمكنهم تجاوزه.
     */
    protected function validate(array $data): void
    {
        // افتراضيًا: لا شيء
    }

    // ==========================================================
    // Helpers
    // ==========================================================

    protected function extractSystemPrompt(AIRequest $request): ?string
    {
        foreach ($request->messages as $message) {
            if ($message->role === 'system') {
                return $message->content;
            }
        }
        return null;
    }

    protected function extractUserPrompt(AIRequest $request): string
    {
        foreach ($request->messages as $message) {
            if ($message->role === 'user') {
                return $message->content;
            }
        }
        return '';
    }

    protected function system(string $content): AIMessage
    {
        return AIMessage::system($content);
    }

    protected function user(string $content): AIMessage
    {
        return AIMessage::user($content);
    }
}