<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$key = app(\App\Services\SecretsManager::class)->get('GEMINI_API_KEY');
$model = 'gemini-2.0-flash';
$url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=".urlencode($key);
$payload = [
    'contents' => [['parts' => [['text' => 'Say OK']]]],
    'generationConfig' => ['maxOutputTokens' => 10],
];

try {
    $r = \Illuminate\Support\Facades\Http::timeout(60)->post($url, $payload);
    echo 'Status: '.$r->status()."\n";
    echo substr($r->body(), 0, 800)."\n";
} catch (\Throwable $e) {
    echo 'Exception: '.$e->getMessage()."\n";
}
