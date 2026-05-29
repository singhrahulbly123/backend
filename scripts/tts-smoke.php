<?php
// Self-contained TTS smoke tester. Writes output to backend/storage/app/public/tts/<job>.mp3

$jobId = $argv[1] ?? bin2hex(random_bytes(4));
$text = $argv[2] ?? "यह एक परीक्षण पाठ है — यह केवल डेमो है।";
$voice = $argv[3] ?? getenv('ELEVENLABS_VOICE') ?: 'alloy';

$outDir = __DIR__ . '/../storage/app/public/tts';
if (!is_dir($outDir)) mkdir($outDir, 0755, true);

$mp3Path = $outDir . "/{$jobId}.mp3";
$statusPath = $outDir . "/{$jobId}.status.json";

function writeStatus($path, $status, $extra = []) {
    $payload = array_merge([
        'job_id' => basename($path, '.status.json'),
        'status' => $status,
        'updated_at' => date(DATE_ATOM),
    ], $extra);
    file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

writeStatus($statusPath, 'processing');

$apiKey = getenv('ELEVENLABS_API_KEY');
if ($apiKey) {
    $base = rtrim(getenv('ELEVENLABS_API_URL') ?: 'https://api.elevenlabs.io/v1/text-to-speech', '/');
    $url = $base . '/' . $voice;
    $payload = json_encode(['text' => $text, 'model' => (getenv('ELEVENLABS_MODEL') ?: 'eleven_monolingual_v1')]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'xi-api-key: ' . $apiKey,
        'Accept: audio/mpeg',
        'Content-Type: application/json',
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);

    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp !== false && $code >= 200 && $code < 300) {
        file_put_contents($mp3Path, $resp);
        writeStatus($statusPath, 'completed', ['audio_path' => 'public/tts/' . basename($mp3Path)]);
        echo "TTS succeeded — wrote {$mp3Path}\n";
        exit(0);
    }

    writeStatus($statusPath, 'failed', ['error' => $err ?: "HTTP {$code}"]);
    echo "TTS request failed: " . ($err ?: "HTTP {$code}") . "\n";
    exit(2);
}

// No API key: create a small placeholder MP3 file (not real audio) so downstream pipeline can run.
$placeholder = "FAKE-MP3-HEADER\n" . $text;
file_put_contents($mp3Path, $placeholder);
writeStatus($statusPath, 'completed', ['audio_path' => 'public/tts/' . basename($mp3Path), 'note' => 'placeholder']);
echo "No API key found — created placeholder MP3: {$mp3Path}\n";
exit(0);
