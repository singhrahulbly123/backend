<?php

namespace App\Jobs;

use App\Services\VideoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $jobId;
    public string $audioPath;
    public string $storySlug;
    public array $options;

    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 180;

    public function __construct(string $jobId, string $audioPath, string $storySlug, array $options = [])
    {
        $this->jobId = $jobId;
        $this->audioPath = $audioPath;
        $this->storySlug = $storySlug;
        $this->options = $options;
    }

    public function handle(VideoService $videoService)
    {
        $this->setStatus('processing');

        $video = $videoService->generateFromAudio($this->audioPath, $this->storySlug, $this->jobId, $this->options);
        if ($video === null) {
            throw new \RuntimeException('Video generation failed for job ' . $this->jobId);
        }

        $this->setStatus('completed', ['output' => $video]);
    }

    public function failed(Throwable $exception): void
    {
        $this->setStatus('failed', ['error' => $exception->getMessage(), 'trace' => $exception->getTraceAsString()]);
        Log::error('GenerateVideoJob failed', ['job_id' => $this->jobId, 'error' => $exception->getMessage()]);
    }

    protected function setStatus(string $status, array $extra = []): void
    {
        $payload = array_merge([
            'job_id' => $this->jobId,
            'status' => $status,
            'story_slug' => $this->storySlug,
            'audio_path' => $this->audioPath,
            'updated_at' => now()->toDateTimeString(),
        ], $extra);

        Storage::disk('public')->put("videos/{$this->jobId}.status.json", json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
