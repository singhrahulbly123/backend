<?php

namespace App\Services\AI;

interface AiProviderInterface
{
    public function name(): string;

    /**
     * @param  array<string, mixed>  $options
     */
    public function chat(array $messages, array $options = []): array;

    /**
     * @param  array<string, mixed>  $options
     */
    public function complete(string $systemPrompt, string $userPrompt, array $options = []): string;
}
