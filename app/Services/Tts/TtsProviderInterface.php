<?php

namespace App\Services\Tts;

interface TtsProviderInterface
{
    public function name(): string;

    public function synthesize(string $text, string $voice = 'alloy'): ?string;
}
