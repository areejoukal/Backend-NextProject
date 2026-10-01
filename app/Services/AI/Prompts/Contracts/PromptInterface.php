<?php

namespace App\Services\AI\Prompts\Contracts;

use App\Services\AI\DTOs\AIRequest;

interface PromptInterface
{
    public function build(): AIRequest;

    public function schema(): ?array;

    public function name(): string;

    public function recommendedModel(): ?string;

    public function temperature(): float;
}