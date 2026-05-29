<?php

namespace App\Jobs;

use App\Models\Article;
use App\Services\ArticleVideoService;
use App\Services\TtsService;
use App\Services\VideoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateArticleReelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $jobId;
    public int $articleId;
    public string $platform;
    public string $voice;
    public bool $includeSubtitles;

    public int $tries = 3;
    public int $backoff = 30;
    public int $timeout = 300;

    public function __construct(string $jobId, int $articleId, string $platform = 'instagram_reel', string $voice = 'alloy', bool $includeSubtitles = true)
    {
        $this->jobId = $jobId;
        $this->articleId = $articleId;
        $this->platform = $platform;
        $this->voice = $voice;
        $this->includeSubtitles = $includeSubtitles;
    }

    public function handle(TtsService $tts, VideoService $videoService, ArticleVideoService $videoPipeline)
    {
        $this->setStatus('processing');

        $article = Article::find($this->articleId);
        if (! $article) {
            throw new \RuntimeException('Article not found for reel generation: ' . $this->articleId);
        }

        $voice = $this->voice ?: $videoPipeline->preferredVoice($article->locale);
        $script = $videoPipeline->buildVoiceScript($article);

        $audio = $tts->generateAudio($script, $voice);
        if ($audio === null) {
            throw new \RuntimeException('TTS generation failed for article reel ' . $this->jobId . '. Check ELEVENLABS_API_KEY and TTS voice settings.');
        }

        $audioPath = "public/tts/{$this->jobId}.mp3";
        Storage::disk('public')->put("tts/{$this->jobId}.mp3", $audio);

        $subtitlePath = null;
        if ($this->includeSubtitles) {
            $subtitle = $videoPipeline->buildSubtitleSrt($article);
            $subtitlePath = $videoPipeline->storeSubtitleFile($subtitle, $this->jobId);
        }

        $options = $videoPipeline->buildVideoOptions($article, $this->platform, $this->includeSubtitles, $subtitlePath);
        $videoOutput = $videoService->generateFromAudio($audioPath, $article->slug, $this->jobId, $options);
        if ($videoOutput === null) {
            throw new \RuntimeException('Video rendering failed for article reel ' . $this->jobId);
        }

        $this->setStatus('completed', [
            'article_id' => $article->id,
            'article_slug' => $article->slug,
            'voice' => $voice,
            'audio_path' => $audioPath,
            'subtitle_path' => $subtitlePath,
            'output' => $videoOutput,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $this->setStatus('failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        Log::error('GenerateArticleReelJob failed', ['job_id' => $this->jobId, 'article_id' => $this->articleId, 'error' => $exception->getMessage()]);
    }

    protected function setStatus(string $status, array $extra = []): void
    {
        $payload = array_merge([
            'job_id' => $this->jobId,
            'status' => $status,
            'article_id' => $this->articleId,
            'platform' => $this->platform,
            'updated_at' => now()->toDateTimeString(),
        ], $extra);

        Storage::disk('public')->put("videos/{$this->jobId}.status.json", json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
