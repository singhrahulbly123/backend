<?php

namespace App\Jobs;

use App\Models\AiTool;
use App\Models\Article;
use App\Models\DailyAiBrief;
use App\Services\ArticleVideoService;
use App\Services\TtsService;
use App\Services\VideoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateGrowthReelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 30;
    public int $timeout = 300;

    public function __construct(
        public string $jobId,
        public string $sourceType,
        public int $sourceId,
        public string $platform = 'instagram_reel',
        public string $voice = 'alloy',
        public bool $includeSubtitles = true,
        public bool $allowStoryboardFallback = true,
    ) {}

    public function handle(TtsService $tts, VideoService $videoService, ArticleVideoService $videoPipeline): void
    {
        $this->setStatus('processing');

        $source = $this->resolveSource();
        if (! $source) {
            throw new \RuntimeException("Source {$this->sourceType}:{$this->sourceId} not found for reel generation.");
        }

        $script = $videoPipeline->buildScriptForSource($this->sourceType, $source);
        $scriptPath = $videoPipeline->storeScriptFile($script, $this->jobId);
        $subtitlePath = null;

        if ($this->includeSubtitles) {
            $subtitlePath = $videoPipeline->storeSubtitleFile(
                $videoPipeline->buildSubtitleSrtFromText($script),
                $this->jobId
            );
        }

        $voice = $this->voice ?: $videoPipeline->preferredVoice($source->locale ?? 'en');
        $options = $videoPipeline->buildVideoOptionsForSource($this->sourceType, $source, $this->platform, $this->includeSubtitles, $subtitlePath);
        $audio = $tts->generateAudio($script, $voice);

        if ($audio === null) {
            if (! $this->allowStoryboardFallback) {
                throw new \RuntimeException('TTS generation failed. Check ELEVENLABS_API_KEY and TTS voice settings.');
            }

            $output = $videoService->generateStoryboardStub($this->jobId, [
                'source_type' => $this->sourceType,
                'source_id' => $this->sourceId,
                'source_slug' => $source->slug ?? null,
                'platform' => $this->platform,
                'voice' => $voice,
                'script_path' => $scriptPath,
                'subtitle_path' => $subtitlePath,
                'options' => $options,
                'script' => $script,
                'note' => 'TTS is not configured or failed; storyboard package generated for manual voiceover/rendering.',
            ]);

            $this->setStatus('completed', $this->statusPayload($source, $voice, $scriptPath, $subtitlePath, null, $output, 'storyboard_json'));
            return;
        }

        $audioPath = "public/tts/{$this->jobId}.mp3";
        Storage::disk('public')->put("tts/{$this->jobId}.mp3", $audio);

        $output = $videoService->generateFromAudio($audioPath, $source->slug ?? $this->sourceType, $this->jobId, $options);
        if ($output === null) {
            throw new \RuntimeException('Video rendering failed for reel ' . $this->jobId);
        }

        $this->setStatus('completed', $this->statusPayload($source, $voice, $scriptPath, $subtitlePath, $audioPath, $output, str_ends_with($output, '.json') ? 'storyboard_json' : 'video'));
    }

    public function failed(Throwable $exception): void
    {
        $this->setStatus('failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        Log::error('GenerateGrowthReelJob failed', [
            'job_id' => $this->jobId,
            'source_type' => $this->sourceType,
            'source_id' => $this->sourceId,
            'error' => $exception->getMessage(),
        ]);
    }

    protected function resolveSource(): ?Model
    {
        return match ($this->sourceType) {
            'article' => Article::find($this->sourceId),
            'ai_tool' => AiTool::find($this->sourceId),
            'daily_brief' => DailyAiBrief::find($this->sourceId),
            default => null,
        };
    }

    protected function statusPayload(Model $source, string $voice, string $scriptPath, ?string $subtitlePath, ?string $audioPath, string $output, string $outputType): array
    {
        return [
            'source_slug' => $source->slug ?? null,
            'voice' => $voice,
            'script_path' => $scriptPath,
            'subtitle_path' => $subtitlePath,
            'audio_path' => $audioPath,
            'output' => $output,
            'output_type' => $outputType,
        ];
    }

    protected function setStatus(string $status, array $extra = []): void
    {
        $payload = array_merge([
            'job_id' => $this->jobId,
            'status' => $status,
            'source_type' => $this->sourceType,
            'source_id' => $this->sourceId,
            'platform' => $this->platform,
            'updated_at' => now()->toDateTimeString(),
        ], $extra);

        Storage::disk('public')->put("videos/{$this->jobId}.status.json", json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
