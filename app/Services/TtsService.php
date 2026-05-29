<?php

namespace App\Services;

use App\Services\Tts\ElevenLabsProvider;
use App\Services\Tts\TtsProviderInterface;
use App\Services\SecretsManager;

class TtsService
{
    protected ?TtsProviderInterface $provider = null;

    public function __construct(private readonly SecretsManager $secrets)
    {
        $this->resolveProvider();
    }

    public function generateAudio(string $text, string $voice = 'alloy'): ?string
    {
        if ($this->provider === null) {
            return null;
        }

        return $this->provider->synthesize($text, $voice);
    }

    protected function resolveProvider(): void
    {
        $provider = strtolower(env('TTS_PROVIDER', 'elevenlabs'));

        if ($provider === 'elevenlabs') {
            $this->provider = app(ElevenLabsProvider::class);
        }
    }
}
