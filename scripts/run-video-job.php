<?php
use App\Jobs\GenerateVideoJob;
use Illuminate\Support\Str;
use Throwable;

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$jobId = $argv[1] ?? Str::random(8);
$audioPath = $argv[2] ?? ('public/tts/' . ($argv[2] ?? 'sample.mp3'));
$slug = $argv[3] ?? 'test-story';

echo "Running Video job: {$jobId}\n";

$job = new GenerateVideoJob($jobId, $audioPath, $slug, ['title' => 'Test Story']);
try {
    $job->handle(app(App\Services\VideoService::class));
    echo "Video job executed. Status file: storage/app/public/videos/{$jobId}.status.json\n";
} catch (Throwable $e) {
    echo "Video job failed: " . $e->getMessage() . "\n";
}
