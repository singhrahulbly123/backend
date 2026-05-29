<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$g = app(\App\Services\AI\GeminiProvider::class);
$r = $g->chat([['role' => 'user', 'content' => 'Say OK']], ['max_tokens' => 10]);
echo json_encode($r, JSON_PRETTY_PRINT);
