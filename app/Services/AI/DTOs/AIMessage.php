<?php

namespace App\Services\AI\DTOs;

class AIMessage
{
    public function __construct(
        public readonly string $role,     // 'system' | 'user' | 'assistant'
        public readonly string $content,
    ) {}

    public static function system(string $content): self
    {
        return new self('system', $content);
    }

    public static function user(string $content): self
    {
        return new self('user', $content);
    }

    public static function assistant(string $content): self
    {
        return new self('assistant', $content);
    }

    public function toArray(): array
    {
        return [
            'role' => $this->role,
            'content' => $this->content,
        ];
    }
}