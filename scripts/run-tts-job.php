<?php
use App\Jobs\GenerateTtsJob;
use Illuminate\Support\Str;
use Throwable;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$jobId = $argv[1] ?? Str::random(8);
$text = $argv[2] ?? "यह एक परीक्षण पाठ है। This is a test TTS audio.";
$voice = $argv[3] ?? env('ELEVENLABS_VOICE', 'alloy');

echo "Running TTS job: {$jobId}\n";

$job = new GenerateTtsJob($jobId, $text, $voice);
try {
    $job->handle(app(App\Services\TtsService::class));
    echo "TTS job executed. Status file: storage/app/public/tts/{$jobId}.status.json\n";
} catch (Throwable $e) {
    echo "TTS job failed: " . $e->getMessage() . "\n";
}
