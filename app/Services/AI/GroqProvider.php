<?php

namespace App\Services\AI;

use App\Services\SecretsManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqProvider implements AiProviderInterface
{
    public function __construct(private readonly SecretsManager $secrets) {}

    public function name(): string
    {
        return 'groq';
    }

    public function complete(string $systemPrompt, string $userPrompt, array $options = []): string
    {
        $result = $this->chat([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ], $options);

        if (! is_array($result) || ! empty($result['error']) || empty($result['content'])) {
            throw new \RuntimeException('Groq provider returned invalid response: '.json_encode($result));
        }

        return $result['content'];
    }

    public function chat(array $messages, array $options = []): array
    {
        $apiKey = $this->getApiKey();
        if (empty($apiKey)) {
            return ['error' => 'GROQ_API_KEY_NOT_SET'];
        }

        $payload = [
            'model' => $options['model'] ?? config('ai.providers.groq.model', 'llama-3.3-70b-versatile'),
            'messages' => $messages,
            'temperature' => $options['temperature'] ?? 0.5,
            'max_tokens' => $options['max_tokens'] ?? 4096,
        ];

        if (! empty($options['json'])) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(120)
                ->post(rtrim(config('ai.providers.groq.base_url', 'https://api.groq.com/openai/v1'), '/').'/chat/completions', $payload)
                ->throw()
                ->json();

            $content = data_get($response, 'choices.0.message.content', '');
            if ($content === '') {
                return ['error' => 'invalid_response', 'response' => $response];
            }

            return [
                'provider' => $this->name(),
                'content' => $content,
                'usage' => $response['usage'] ?? [],
            ];
        } catch (\Throwable $e) {
            Log::warning('Groq provider request failed', ['error' => $e->getMessage()]);
            return ['error' => 'groq_request_failed', 'message' => $e->getMessage()];
        }
    }

    protected function getApiKey(): ?string
    {
        return $this->secrets->get('GROQ_API_KEY', config('ai.providers.groq.api_key'));
    }
}
