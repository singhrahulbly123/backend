<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\AI\OpenAiProvider;
use App\Services\AI\GeminiProvider;
use App\Services\AI\GroqProvider;
use App\Services\SecretsManager;
use Illuminate\Support\Facades\Log;

class ProvidersHealth extends Command
{
    protected $signature = 'ai:providers:health';
    protected $description = 'Check health of configured AI providers';

    public function handle(OpenAiProvider $open, GeminiProvider $gemini, GroqProvider $groq, SecretsManager $secrets)
    {
        $providers = [
            $open->name() => $open,
            $gemini->name() => $gemini,
            $groq->name() => $groq,
        ];

        $results = [];

        foreach ($providers as $key => $provider) {
            $secretKey = strtoupper($key) . '_API_KEY';
            $keyConfigured = ! empty($secrets->get($secretKey));

            $this->info('Checking ' . $key . ($keyConfigured ? ' (key configured)' : ' (key missing)'));
            try {
                $res = $provider->chat([['role' => 'system', 'content' => 'You are a test agent. Reply with OK.'], ['role' => 'user', 'content' => 'ping']], ['max_tokens' => 10]);
                $ok = isset($res['content']) && str_contains(strtolower($res['content']), 'ok');
                $results[$key] = ['ok' => $ok, 'key_configured' => $keyConfigured, 'raw' => $this->sanitizeResult($res)];
                $this->info($key . ': ' . ($ok ? 'OK' : 'FAILED'));
                if (! $ok && isset($res['message'])) {
                    $this->warn('Reason: ' . $this->sanitizeMessage((string) $res['message']));
                } elseif (! $ok && isset($res['error'])) {
                    $this->warn('Reason: ' . $this->sanitizeMessage((string) $res['error']));
                }
            } catch (\Throwable $e) {
                $results[$key] = ['ok' => false, 'key_configured' => $keyConfigured, 'error' => $this->sanitizeMessage($e->getMessage())];
                $this->error($key . ': EXCEPTION');
            }
        }

        Log::info('AI providers health', $results);

        $this->line('Health check complete.');

        return collect($results)->contains(fn (array $result) => $result['ok'] === true)
            ? self::SUCCESS
            : self::FAILURE;
    }

    protected function sanitizeResult(array $result): array
    {
        array_walk_recursive($result, function (&$value): void {
            if (is_string($value)) {
                $value = $this->sanitizeMessage($value);
            }
        });

        return $result;
    }

    protected function sanitizeMessage(string $message): string
    {
        return preg_replace('/([?&]key=)[^&\s]+/i', '$1[redacted]', $message) ?? $message;
    }
}
