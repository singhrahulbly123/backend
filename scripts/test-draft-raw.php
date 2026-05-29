<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$orch = app(\App\Services\AI\AiOrchestrator::class);
$system = 'Return valid JSON only with keys: title, excerpt, ai_summary, key_points, body, tags, faqs.';
$user = json_encode(['topic' => 'AI Hindi news', 'locale' => 'hi']);

foreach (['groq', 'openai', 'gemini'] as $p) {
    try {
        $raw = $orch->run('test', $system, $user, $p, ['json' => true]);
        echo "\n--- $p ---\n";
        echo substr($raw, 0, 500)."\n";
    } catch (\Throwable $e) {
        echo "\n--- $p FAIL ---\n".$e->getMessage()."\n";
    }
}
