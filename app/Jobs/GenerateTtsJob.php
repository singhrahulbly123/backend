<?php

namespace App\Jobs;

use App\Services\TtsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateTtsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $jobId;
    public string $text;
    public string $voice;

    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 120;

    public function __construct(string $jobId, string $text, string $voice = 'alloy')
    {
        $this->jobId = $jobId;
        $this->text = $text;
        $this->voice = $voice;
    }

    public function handle(TtsService $tts)
    {
        $this->setStatus('processing');

        $audio = $tts->generateAudio($this->text, $this->voice);
        if ($audio === null) {
            throw new \RuntimeException('TTS generation failed for job ' . $this->jobId . '. Check ELEVENLABS_API_KEY and TTS voice settings.');
        }

        $path = "public/tts/{$this->jobId}.mp3";
        Storage::disk('public')->put("tts/{$this->jobId}.mp3", $audio);
        $this->setStatus('completed', ['audio_path' => $path]);
    }

    public function failed(Throwable $exception): void
    {
        $this->setStatus('failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
        Log::error('GenerateTtsJob failed', ['job_id' => $this->jobId, 'error' => $exception->getMessage()]);
    }

    protected function setStatus(string $status, array $extra = []): void
    {
        $payload = array_merge([
            'job_id' => $this->jobId,
            'status' => $status,
            'voice' => $this->voice,
            'updated_at' => now()->toDateTimeString(),
        ], $extra);

        Storage::disk('public')->put("tts/{$this->jobId}.status.json", json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
