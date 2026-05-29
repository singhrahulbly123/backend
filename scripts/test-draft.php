<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $draft = app(\App\Services\AI\NewsAiService::class)->generateDraft([
        'topic' => 'AI Hindi news test',
        'locale' => 'hi',
    ]);
    echo 'Keys: '.implode(', ', array_keys($draft))."\n";
    echo 'Has body: '.(empty($draft['body']) ? 'NO' : 'YES len='.strlen($draft['body']))."\n";
    echo 'Title: '.($draft['title'] ?? '(none)')."\n";
} catch (\Throwable $e) {
    echo 'FAIL: '.$e->getMessage()."\n";
}
