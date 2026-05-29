<?php

namespace App\Services\Tts;

use App\Services\SecretsManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use App\Services\Tts\TtsProviderInterface;

class ElevenLabsProvider implements TtsProviderInterface
{
    public function __construct(private readonly SecretsManager $secrets) {}

    public function name(): string
    {
        return 'elevenlabs';
    }

    public function synthesize(string $text, string $voice = 'alloy'): ?string
    {
        $apiKey = $this->getApiKey();
        if (empty($apiKey)) {
            Log::warning('ElevenLabsProvider missing API key');
            return null;
        }

        $baseUrl = $this->secrets->get('ELEVENLABS_API_URL', env('ELEVENLABS_API_URL', 'https://api.elevenlabs.io/v1/text-to-speech'));
        $url = rtrim($baseUrl, '/') . '/' . $this->resolveVoiceId($voice);
        $model = $this->secrets->get('ELEVENLABS_MODEL', env('ELEVENLABS_MODEL', 'eleven_monolingual_v1'));

        try {
            $response = Http::withHeaders([
                'xi-api-key' => $apiKey,
                'Accept' => 'audio/mpeg',
                'Content-Type' => 'application/json',
            ])
                ->timeout(120)
                ->post($url, [
                    'text' => $text,
                    'model' => $model,
                ]);

            if ($response->failed()) {
                $message = sprintf('ElevenLabs TTS request failed (status %s): %s', $response->status(), $response->body());
                Log::warning($message, [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new \RuntimeException($message);
            }

            return $response->body();
        } catch (\Throwable $e) {
            Log::warning('ElevenLabs TTS request failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    protected function getApiKey(): ?string
    {
        return $this->secrets->get('ELEVENLABS_API_KEY', env('ELEVENLABS_API_KEY'));
    }

    protected function resolveVoiceId(string $voice): string
    {
        $fallback = $this->secrets->get('ELEVENLABS_VOICE_ID', env('ELEVENLABS_VOICE_ID', '21m00Tcm4TlvDq8ikWAM'));

        return match ($voice) {
            'hi-IN-Standard-A', 'hi-IN-Standard-B', 'alloy', '' => $fallback,
            default => $voice,
        };
    }
}
