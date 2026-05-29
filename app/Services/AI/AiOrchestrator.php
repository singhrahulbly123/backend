<?php

namespace App\Services\AI;

use App\Models\AiLog;
use Illuminate\Support\Facades\Log;

class AiOrchestrator
{
    public function __construct(
        private readonly OpenAiProvider $openAi,
        private readonly GeminiProvider $gemini,
        private readonly GroqProvider $groq,
        private readonly ProviderRateLimiter $rateLimiter,
        private readonly BillingService $billing,
    ) {}

    public function chat(array $messages, array $options = []): array
    {
        $order = $this->buildProviderOrder();
        $retries = max(1, (int) env('AI_PROVIDER_RETRIES', 1));
        $baseBackoffMs = max(100, (int) env('AI_PROVIDER_BACKOFF_MS', 200));
        $errors = [];

        foreach ($order as $providerKey => $provider) {
            if (! $this->rateLimiter->allow($providerKey)) {
                $errors[$providerKey][] = 'rate_limited';
                Log::info('Skipping provider due to rate limit', ['provider' => $providerKey]);
                continue;
            }

            for ($attempt = 1; $attempt <= $retries; $attempt++) {
                try {
                    $response = $provider->chat($messages, $options);

                    if (is_array($response) && empty($response['error']) && isset($response['content'])) {
                        $this->recordUsage($providerKey, $response, null);
                        return $response;
                    }

                    $errorCode = is_array($response) ? ($response['error'] ?? 'invalid_response') : 'invalid_response';
                    $errors[$providerKey][] = "attempt{$attempt}:{$errorCode}";
                    Log::warning('AI provider returned error', ['provider' => $providerKey, 'attempt' => $attempt, 'error' => $errorCode, 'response' => $response]);
                } catch (\Throwable $e) {
                    $errors[$providerKey][] = "attempt{$attempt}:exception:{$e->getMessage()}";
                    Log::warning('AI provider exception', ['provider' => $providerKey, 'attempt' => $attempt, 'exception' => $e->getMessage()]);
                }

                if ($attempt < $retries) {
                    $sleepMs = $baseBackoffMs * (2 ** ($attempt - 1));
                    usleep($sleepMs * 1000);
                }
            }
        }

        Log::error('All AI providers failed', ['errors' => $errors]);

        return ['error' => 'all_providers_failed', 'details' => $errors];
    }

    public function provider(?string $name = null): AiProviderInterface
    {
        return match ($name ?? config('ai.default')) {
            'gemini' => $this->gemini,
            'groq' => $this->groq,
            default => $this->openAi,
        };
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function run(
        string $operation,
        string $systemPrompt,
        string $userPrompt,
        ?string $provider = null,
        array $options = [],
        ?int $userId = null,
        ?string $loggableType = null,
        ?int $loggableId = null,
    ): string {
        $start = microtime(true);
        $providers = $this->buildProviderOrder($provider);
        $errors = [];

        foreach ($providers as $providerKey => $ai) {
            if (! $this->rateLimiter->allow($providerKey)) {
                $errors[$providerKey][] = 'rate_limited';
                Log::info('Skipping provider due to rate limit', ['provider' => $providerKey]);
                continue;
            }

            try {
                $response = $ai->chat([
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ], $options);

                if (! is_array($response) || ! empty($response['error']) || empty($response['content'])) {
                    $errorCode = is_array($response) ? ($response['error'] ?? 'invalid_response') : 'invalid_response';
                    $errors[$providerKey][] = $errorCode;
                    Log::warning('AI provider returned invalid result', ['provider' => $providerKey, 'error' => $errorCode, 'response' => $response]);
                    continue;
                }

                $result = $response['content'];
                $this->recordUsage($providerKey, $response, $operation);

                AiLog::create([
                    'provider' => $providerKey,
                    'operation' => $operation,
                    'loggable_type' => $loggableType,
                    'loggable_id' => $loggableId,
                    'user_id' => $userId,
                    'request_payload' => ['system' => $systemPrompt, 'user' => $userPrompt],
                    'response_payload' => ['content' => mb_substr($result, 0, 5000), 'meta' => $response],
                    'tokens_used' => $this->extractTokens($response),
                    'cost_usd' => 0.0,
                    'duration_ms' => (int) ((microtime(true) - $start) * 1000),
                    'status' => 'success',
                ]);

                return $result;
            } catch (\Throwable $e) {
                $errors[$providerKey][] = "exception:{$e->getMessage()}";
                Log::warning('AI provider exception during run', ['provider' => $providerKey, 'exception' => $e->getMessage()]);
            }
        }

        AiLog::create([
            'provider' => $provider ?? config('ai.default'),
            'operation' => $operation,
            'loggable_type' => $loggableType,
            'loggable_id' => $loggableId,
            'user_id' => $userId,
            'request_payload' => ['system' => $systemPrompt, 'user' => $userPrompt],
            'duration_ms' => (int) ((microtime(true) - $start) * 1000),
            'status' => 'failed',
            'error_message' => json_encode($errors),
        ]);

        Log::error('AI operation failed after fallback', ['operation' => $operation, 'errors' => $errors]);

        $details = [];
        $allMissingKeys = true;

        foreach ($errors as $providerKey => $providerErrors) {
            $details[] = $providerKey.': '.implode(', ', $providerErrors);
            foreach ($providerErrors as $errorItem) {
                if (! str_contains($errorItem, 'API_KEY_NOT_SET')) {
                    $allMissingKeys = false;
                }
            }
        }

        if ($allMissingKeys) {
            throw new \RuntimeException('No AI provider API keys are configured. Add OPENAI_API_KEY, GEMINI_API_KEY, or GROQ_API_KEY in env or Admin Settings.');
        }

        $detailText = $details ? ' Details: '.implode(' | ', $details) : '';
        throw new \RuntimeException('All configured AI providers failed for operation: '.$operation.'.'.$detailText, 0, null);
    }

    protected function recordUsage(string $provider, array $response, ?string $operation = null): void
    {
        try {
            $tokens = $this->extractTokens($response);
            $this->billing->recordUsage($provider, $tokens, $response, $operation);
        } catch (\Throwable $e) {
            Log::warning('Billing record failed', ['error' => $e->getMessage()]);
        }
    }

    protected function extractTokens(array $response): int
    {
        if (isset($response['usage']['total_tokens'])) {
            return (int) $response['usage']['total_tokens'];
        }

        if (isset($response['usage']['token_count'])) {
            return (int) $response['usage']['token_count'];
        }

        return 0;
    }

    protected function buildProviderOrder(?string $preferredProvider = null): array
    {
        $orderEnv = env('AI_PROVIDER_ORDER', 'openai,gemini,groq');
        $order = array_filter(array_map('trim', explode(',', $orderEnv)));

        $providers = [
            'openai' => $this->openAi,
            'gemini' => $this->gemini,
            'groq' => $this->groq,
        ];

        if ($preferredProvider) {
            $preferredProvider = strtolower($preferredProvider);
            if (isset($providers[$preferredProvider])) {
                $order = array_unique(array_merge([$preferredProvider], $order));
            }
        }

        $sorted = [];
        foreach ($order as $providerKey) {
            $providerKey = strtolower($providerKey);
            if (isset($providers[$providerKey]) && ! isset($sorted[$providerKey])) {
                $sorted[$providerKey] = $providers[$providerKey];
            }
        }

        return $sorted;
    }
}
