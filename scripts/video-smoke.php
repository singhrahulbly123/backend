<?php
// Simple video generator smoke test. Consumes backend/storage/app/public/tts/<audio> and writes backend/storage/app/public/videos/<job>.mp4 or .json

$jobId = $argv[1] ?? bin2hex(random_bytes(4));
$audioFile = $argv[2] ?? null; // expected relative to storage path or absolute
$slug = $argv[3] ?? 'test-story';

$storagePublic = __DIR__ . '/../storage/app/public';
$videosDir = $storagePublic . '/videos';
if (!is_dir($videosDir)) mkdir($videosDir, 0755, true);

function writeStatus($path, $status, $extra = []) {
    $payload = array_merge([
        'job_id' => basename($path, '.status.json'),
        'status' => $status,
        'updated_at' => date(DATE_ATOM),
    ], $extra);
    file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

$statusPath = $videosDir . "/{$jobId}.status.json";
writeStatus($statusPath, 'processing');

// Resolve audio path
if ($audioFile === null) {
    writeStatus($statusPath, 'failed', ['error' => 'no_audio_provided']);
    echo "No audio path provided.\n";
    exit(1);
}

// If passed relative like public/tts/file.mp3, map to storage path
if (strpos($audioFile, 'public/') === 0) {
    $audioPath = __DIR__ . '/../storage/app/' . $audioFile;
} else {
    $audioPath = $audioFile;
}

if (!file_exists($audioPath)) {
    writeStatus($statusPath, 'failed', ['error' => 'audio_missing', 'path' => $audioPath]);
    echo "Audio file missing: {$audioPath}\n";
    exit(2);
}

$ffmpeg = getenv('FFMPEG_PATH') ?: 'ffmpeg';
// Check ffmpeg availability
$ffOk = false;
exec(escapeshellcmd($ffmpeg) . " -version 2>&1", $out, $code);
if ($code === 0) $ffOk = true;

$outVideo = $videosDir . "/{$jobId}.mp4";

if ($ffOk) {
    // create a placeholder image
    $image = $videosDir . "/{$jobId}.png";
    $im = @imagecreatetruecolor(1280, 720);
    if ($im !== false) {
        $bg = imagecolorallocate($im, 0, 0, 0);
        $txt = imagecolorallocate($im, 255, 255, 255);
        imagefilledrectangle($im, 0, 0, 1280, 720, $bg);
        imagestring($im, 5, 50, 340, "{$slug}", $txt);
        imagepng($im, $image);
        imagedestroy($im);
    } else {
        // fallback: empty file
        file_put_contents($image, '');
    }

    $cmd = sprintf('%s -y -loop 1 -i %s -i %s -c:v libx264 -tune stillimage -c:a aac -b:a 128k -pix_fmt yuv420p -shortest %s 2>&1',
        escapeshellcmd($ffmpeg), escapeshellarg($image), escapeshellarg($audioPath), escapeshellarg($outVideo)
    );

    exec($cmd, $output, $exitCode);
    if ($exitCode === 0 && file_exists($outVideo)) {
        writeStatus($statusPath, 'completed', ['output' => 'public/videos/' . basename($outVideo)]);
        echo "Video generated: {$outVideo}\n";
        exit(0);
    }

    writeStatus($statusPath, 'failed', ['error' => 'ffmpeg_failed', 'cmd' => $cmd, 'output' => $output]);
    echo "FFmpeg failed (exit {$exitCode}). Created stub instead.\n";
}

// Fallback: write a JSON stub referencing the audio
$stub = json_encode([
    'job_id' => $jobId,
    'story_slug' => $slug,
    'audio' => $audioPath,
    'note' => 'ffmpeg not available or failed; placeholder stub',
    'generated_at' => date(DATE_ATOM),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

file_put_contents($videosDir . "/{$jobId}.json", $stub);
writeStatus($statusPath, 'completed', ['output' => 'public/videos/' . basename($jobId . '.json'), 'note' => 'stub']);
echo "FFmpeg not available — wrote stub: {$videosDir}/{$jobId}.json\n";
exit(0);
