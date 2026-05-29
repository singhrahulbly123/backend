<?php

namespace App\Services;

use App\Models\AiTool;
use App\Models\Article;
use App\Models\DailyAiBrief;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArticleVideoService
{
    public function preflight(): array
    {
        $ffmpeg = env('FFMPEG_PATH', 'ffmpeg');

        return [
            'tts_configured' => filled(env('ELEVENLABS_API_KEY')),
            'ffmpeg_path' => $ffmpeg,
            'supports_storyboard_fallback' => true,
            'sources' => ['article', 'ai_tool', 'daily_brief'],
            'platforms' => [
                'instagram_reel' => ['width' => 1080, 'height' => 1920],
                'youtube_short' => ['width' => 1080, 'height' => 1920],
                'facebook_reel' => ['width' => 1080, 'height' => 1920],
                'landscape' => ['width' => 1280, 'height' => 720],
            ],
        ];
    }

    public function buildVoiceScript(Article $article): string
    {
        $summary = $article->excerpt ?: $this->plainText($article->body);
        $body = $this->plainText($article->body);

        return trim(implode("\n\n", array_filter([
            'Create a clear 35-second English voiceover for this Global AI News update.',
            "Title: {$article->title}",
            $summary ? "Summary: {$summary}" : null,
            $body ? 'Details: ' . Str::limit($body, 700) : null,
            'End with: Follow Global AI News for practical AI updates.',
        ])));
    }

    public function buildToolVoiceScript(AiTool $tool): string
    {
        return trim(implode("\n\n", array_filter([
            "AI tool spotlight: {$tool->name}.",
            $tool->tagline,
            $tool->description ? 'What it does: ' . Str::limit($tool->description, 450) : null,
            $tool->pricing ? "Pricing: {$tool->pricing}." : null,
            $this->bulletSentence('Best for', $tool->best_for ?? []),
            $this->bulletSentence('Top benefits', $tool->pros ?? []),
            'Read the full review on Global AI News before choosing a paid plan.',
        ])));
    }

    public function buildBriefVoiceScript(DailyAiBrief $brief): string
    {
        return trim(implode("\n\n", array_filter([
            "Daily AI Brief: {$brief->title}.",
            $brief->summary,
            $this->bulletSentence('Top updates', $brief->key_updates ?? []),
            $brief->impact_india ? 'Global impact: ' . Str::limit($brief->impact_india, 350) : null,
            $this->bulletSentence('Prompts to try', $brief->prompts ?? []),
            'Read the complete daily brief on Global AI News.',
        ])));
    }

    public function buildScriptForSource(string $sourceType, Model $source): string
    {
        return match ($sourceType) {
            'article' => $this->buildVoiceScript($source),
            'ai_tool' => $this->buildToolVoiceScript($source),
            'daily_brief' => $this->buildBriefVoiceScript($source),
            default => throw new \InvalidArgumentException("Unsupported reel source type: {$sourceType}"),
        };
    }

    public function buildSubtitleSrt(Article $article): string
    {
        $text = $article->excerpt ?: $this->plainText($article->body);
        return $this->buildSubtitleSrtFromText($text ?: $article->title);
    }

    public function buildSubtitleSrtFromText(string $text): string
    {
        $sentences = preg_split('/(?<=[!?\.])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $sentences = array_filter(array_map('trim', $sentences ?: []));
        if (empty($sentences)) {
            $sentences = [$text];
        }

        $segments = array_slice($sentences, 0, 12);
        $srt = '';
        $startSeconds = 0;

        foreach ($segments as $index => $segment) {
            $duration = max(2, min(5, (int) ceil(mb_strlen($segment, 'UTF-8') / 20)));
            $start = $this->formatSrtTimestamp($startSeconds);
            $end = $this->formatSrtTimestamp($startSeconds + $duration);
            $srt .= ($index + 1) . "\n";
            $srt .= "{$start} --> {$end}\n";
            $srt .= $segment . "\n\n";
            $startSeconds += $duration;
        }

        return trim($srt);
    }

    public function storeSubtitleFile(string $subtitle, string $jobId): string
    {
        $path = "public/videos/{$jobId}.srt";
        Storage::disk('public')->put("videos/{$jobId}.srt", $subtitle);
        return $path;
    }

    public function storeScriptFile(string $script, string $jobId): string
    {
        $path = "public/videos/{$jobId}.script.txt";
        Storage::disk('public')->put("videos/{$jobId}.script.txt", $script);
        return $path;
    }

    public function buildVideoOptions(Article $article, string $platform, bool $includeSubtitles, ?string $subtitlePath = null): array
    {
        return $this->buildVideoOptionsForSource('article', $article, $platform, $includeSubtitles, $subtitlePath);
    }

    public function buildVideoOptionsForSource(string $sourceType, Model $source, string $platform, bool $includeSubtitles, ?string $subtitlePath = null): array
    {
        $preset = $this->platformPreset($platform);

        return array_filter([
            'width' => $preset['width'],
            'height' => $preset['height'],
            'title' => $this->buildSourceVideoTitle($sourceType, $source, $platform),
            'subtitle_path' => $includeSubtitles && $subtitlePath ? $subtitlePath : null,
            'source_type' => $sourceType,
        ], fn ($value) => $value !== null);
    }

    public function preferredVoice(string $locale): string
    {
        return 'alloy';
    }

    protected function platformPreset(string $platform): array
    {
        return match ($platform) {
            'youtube_short', 'instagram_reel', 'facebook_reel' => ['width' => 1080, 'height' => 1920],
            default => ['width' => 1280, 'height' => 720],
        };
    }

    protected function buildSourceVideoTitle(string $sourceType, Model $source, string $platform): string
    {
        $label = match ($platform) {
            'youtube_short' => 'YouTube Short',
            'instagram_reel' => 'Instagram Reel',
            'facebook_reel' => 'Facebook Reel',
            default => 'Short Video',
        };

        $title = $source->title ?? $source->name ?? 'Global AI News';
        $sourceLabel = match ($sourceType) {
            'ai_tool' => 'AI Tool',
            'daily_brief' => 'Daily Brief',
            default => 'Article',
        };

        return "{$label} {$sourceLabel}: {$title}";
    }

    protected function bulletSentence(string $label, array $items): ?string
    {
        $items = array_values(array_filter(array_map(fn ($item) => trim((string) $item), $items)));
        if (! $items) {
            return null;
        }

        return $label . ': ' . implode('; ', array_slice($items, 0, 4)) . '.';
    }

    protected function plainText(string $html): string
    {
        $text = strip_tags($html);
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim($text);
    }

    protected function formatSrtTimestamp(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $seconds = $seconds % 60;

        return sprintf('%02d:%02d:%02d,000', $hours, $minutes, $seconds);
    }
}
