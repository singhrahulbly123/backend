<?php

namespace App\Services\AI;

use App\Services\SecretsManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiProvider implements AiProviderInterface
{
    public function __construct(private readonly SecretsManager $secrets) {}

    public function name(): string
    {
        return 'gemini';
    }

    public function complete(string $systemPrompt, string $userPrompt, array $options = []): string
    {
        $result = $this->chat([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ], $options);

        if (! is_array($result) || ! empty($result['error']) || empty($result['content'])) {
            throw new \RuntimeException('Gemini provider returned invalid response: '.json_encode($result));
        }

        return $result['content'];
    }

    public function chat(array $messages, array $options = []): array
    {
        $apiKey = $this->getApiKey();
        if (empty($apiKey)) {
            return ['error' => 'GEMINI_API_KEY_NOT_SET', 'message' => 'GEMINI_API_KEY is not configured in Settings or .env'];
        }

        $model = $options['model'] ?? config('ai.providers.gemini.model', 'gemini-2.0-flash');
        $text = collect($messages)
            ->map(function ($message) {
                $role = $message['role'] ?? 'user';
                $content = $message['content'] ?? '';

                return ($role === 'system' ? "System:\n" : '').$content;
            })
            ->filter()
            ->implode("\n\n");

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $text],
                    ],
                ],
            ],
            'generationConfig' => [
                'temperature' => $options['temperature'] ?? 0.7,
                'maxOutputTokens' => $options['max_tokens'] ?? 1024,
            ],
        ];

        try {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";
            $response = Http::timeout(120)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url.'?key='.urlencode($apiKey), $payload)
                ->throw()
                ->json();

            $content = data_get($response, 'candidates.0.content.parts.0.text', '');
            if ($content === '') {
                $blockReason = data_get($response, 'promptFeedback.blockReason');
                if ($blockReason) {
                    return ['error' => 'content_blocked', 'message' => "Gemini blocked content: {$blockReason}"];
                }

                return ['error' => 'invalid_response', 'message' => 'Gemini returned empty content', 'response' => $response];
            }

            return [
                'provider' => $this->name(),
                'content' => $content,
                'usage' => data_get($response, 'usageMetadata', []),
            ];
        } catch (\Throwable $e) {
            $message = $this->sanitizeErrorMessage($e->getMessage());
            Log::warning('Gemini provider request failed', ['error' => $message]);
            if (str_contains($message, '429')) {
                $message = 'Gemini rate limit exceeded. Wait and retry or use another provider.';
            }

            return ['error' => 'gemini_request_failed', 'message' => $message];
        }
    }

    protected function getApiKey(): ?string
    {
        return $this->secrets->get('GEMINI_API_KEY', config('ai.providers.gemini.api_key'));
    }

    protected function sanitizeErrorMessage(string $message): string
    {
        return preg_replace('/([?&]key=)[^&\s]+/i', '$1[redacted]', $message) ?? $message;
    }
}
