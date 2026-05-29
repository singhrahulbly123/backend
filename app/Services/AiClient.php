<?php

namespace App\Services;

class AiClient
{
    protected $orchestrator;

    public function __construct(\App\Services\AI\AiOrchestrator $orchestrator)
    {
        $this->orchestrator = $orchestrator;
    }

    /**
     * Delegate chat requests to the orchestrator which picks the provider.
     */
    public function chat(array $messages, array $options = []): array
    {
        $result = $this->orchestrator->chat($messages, $options);

        if (isset($result['choices'])) {
            return $result;
        }

        if (! empty($result['content'])) {
            return [
                'id' => $result['id'] ?? 'aihindinews-chat',
                'object' => 'chat.completion',
                'provider' => $result['provider'] ?? null,
                'choices' => [
                    [
                        'index' => 0,
                        'message' => [
                            'role' => 'assistant',
                            'content' => $result['content'],
                        ],
                        'finish_reason' => 'stop',
                    ],
                ],
                'usage' => $result['usage'] ?? [],
            ];
        }

        return [
            'id' => 'aihindinews-chat-error',
            'object' => 'chat.completion',
            'error' => $result['error'] ?? 'ai_provider_unavailable',
            'details' => $result['details'] ?? null,
            'choices' => [],
        ];
    }
}
