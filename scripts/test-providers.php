<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

foreach (['openai' => \App\Services\AI\OpenAiProvider::class, 'gemini' => \App\Services\AI\GeminiProvider::class, 'groq' => \App\Services\AI\GroqProvider::class] as $label => $class) {
    $provider = app($class);
    $key = match ($label) {
        'openai' => $app->make(\App\Services\SecretsManager::class)->get('OPENAI_API_KEY'),
        'gemini' => $app->make(\App\Services\SecretsManager::class)->get('GEMINI_API_KEY'),
        'groq' => $app->make(\App\Services\SecretsManager::class)->get('GROQ_API_KEY'),
    };
    $masked = $key ? (substr($key, 0, 8).'...'.substr($key, -4)) : '(empty)';
    echo "\n=== {$label} key: {$masked} ===\n";
    $r = $provider->chat([['role' => 'user', 'content' => 'Reply with OK only']], ['max_tokens' => 10]);
    if (! empty($r['error'])) {
        echo "ERROR: {$r['error']}\n";
        if (! empty($r['message'])) {
            echo "MESSAGE: ".substr($r['message'], 0, 500)."\n";
        }
    } else {
        echo "OK content: ".substr($r['content'] ?? '', 0, 100)."\n";
    }
}
