<?php

namespace App\Services\AI\Contracts;

use App\Services\AI\DTOs\AIRequest;
use App\Services\AI\DTOs\AIResponse;

interface AIProviderInterface
{
    public function chat(AIRequest $request): AIResponse;

    public function chatJson(AIRequest $request, array $schema): array;
}