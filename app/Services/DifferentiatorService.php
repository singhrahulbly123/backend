<?php

namespace App\Services;

use App\Models\AiTool;
use App\Models\Article;
use App\Models\DailyAiBrief;
use Illuminate\Support\Str;

class DifferentiatorService
{
    public const ROLES = ['student', 'creator', 'job_seeker', 'business'];

    public function articleScores(Article $article): array
    {
        return [
            'india_impact_score' => $article->india_impact_score ?? $this->scoreText($article->title.' '.$article->excerpt, ['global', 'world', 'markets', 'technology', 'business']),
            'india_impact_summary' => $article->india_impact_summary ?: $this->fallbackIndiaImpact($article),
            'ai_opportunity_score' => $article->ai_opportunity_score ?? $this->scoreText($article->title.' '.$article->ai_summary, ['jobs', 'creator', 'business', 'money', 'tool']),
            'ai_opportunity_summary' => $article->ai_opportunity_summary ?: $this->fallbackOpportunity($article),
            'audience_roles' => $article->audience_roles ?: $this->inferRoles($article->title.' '.$article->excerpt.' '.$article->ai_summary),
        ];
    }

    public function toolScores(AiTool $tool): array
    {
        $trustBreakdown = $tool->trust_breakdown ?: [
            'pricing_clarity' => $this->containsAny($tool->pricing ?? '', ['free', 'paid', 'trial']) ? 82 : 68,
            'privacy_signal' => $this->containsAny($tool->description ?? '', ['source', 'research', 'enterprise', 'official']) ? 78 : 70,
            'usefulness' => min(95, 65 + (int) (($tool->rating ?? 4.5) * 6)),
            'alternatives' => count($tool->alternatives ?? []) > 0 ? 84 : 62,
        ];

        return [
            'trust_score' => $tool->trust_score ?? (int) round(array_sum($trustBreakdown) / max(1, count($trustBreakdown))),
            'trust_breakdown' => $trustBreakdown,
            'opportunity_score' => $tool->opportunity_score ?? $this->scoreText($tool->name.' '.$tool->description.' '.implode(' ', $tool->use_cases ?? []), ['job', 'resume', 'creator', 'business', 'money', 'automation']),
            'opportunity_summary' => $tool->opportunity_summary ?: "{$tool->name} can save time for ".implode(', ', $tool->best_for ?: ['students', 'creators'])." when used with clear prompts and fact checks.",
            'audience_roles' => $tool->audience_roles ?: $this->inferRoles($tool->name.' '.$tool->description.' '.implode(' ', $tool->best_for ?? [])),
        ];
    }

    public function personalizedFeed(string $role, int $limit = 12): array
    {
        $role = in_array($role, self::ROLES, true) ? $role : 'creator';

        $articles = Article::published()
            ->with(['category', 'author.authorProfile', 'tags', 'seoMeta'])
            ->latest('published_at')
            ->limit($limit * 2)
            ->get()
            ->map(fn (Article $article) => [
                'type' => 'article',
                'score' => $this->roleScore($role, $this->articleScores($article)['audience_roles'], $article->ai_opportunity_score ?? 70, $article->india_impact_score ?? 70),
                'item' => new \App\Http\Resources\ArticleResource($article),
            ]);

        $tools = AiTool::published()
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (AiTool $tool) => [
                'type' => 'tool',
                'score' => $this->roleScore($role, $this->toolScores($tool)['audience_roles'], $tool->opportunity_score ?? 70, $tool->trust_score ?? 70),
                'item' => [
                    ...$tool->toArray(),
                    ...$this->toolScores($tool),
                ],
            ]);

        return [
            'role' => $role,
            'items' => $articles->merge($tools)
                ->sortByDesc('score')
                ->values()
                ->take($limit)
                ->all(),
        ];
    }

    public function voiceBrief(DailyAiBrief $brief): array
    {
        $script = $brief->voice_script ?: $this->buildVoiceScript($brief);

        return [
            'title' => $brief->title,
            'slug' => $brief->slug,
            'script' => $script,
            'audio_url' => $brief->voice_audio_url,
            'duration_seconds' => $brief->voice_duration_seconds ?: max(35, (int) ceil(str_word_count(strip_tags($script)) / 2.2)),
            'published_at' => $brief->published_at?->toIso8601String(),
        ];
    }

    protected function buildVoiceScript(DailyAiBrief $brief): string
    {
        $updates = collect($brief->key_updates ?? [])->take(4)->map(fn ($item, $index) => ($index + 1).'. '.$item)->implode(' ');
        $prompts = collect($brief->prompts ?? [])->take(2)->implode(' ');
        $tool = is_array($brief->tool_of_day) ? ($brief->tool_of_day['name'] ?? null) : null;

        return trim(implode(' ', array_filter([
            "Here is today's Global AI News voice brief.",
            $brief->summary,
            $updates ? "Key updates: {$updates}" : null,
            $brief->impact_india ? "Global impact: {$brief->impact_india}" : null,
            $tool ? "Tool of the day: {$tool}." : null,
            $prompts ? "Prompts to try: {$prompts}" : null,
        ])));
    }

    protected function fallbackIndiaImpact(Article $article): string
    {
        return "This story may affect global AI adoption, jobs, creators, business productivity, or technology policy.";
    }

    protected function fallbackOpportunity(Article $article): string
    {
        return "Opportunity angle: is update ko learning, content creation, automation, ya business efficiency me apply karke real value nikali ja sakti hai.";
    }

    protected function inferRoles(string $text): array
    {
        $text = Str::lower($text);
        $roles = [];
        if ($this->containsAny($text, ['student', 'learn', 'course', 'study'])) $roles[] = 'student';
        if ($this->containsAny($text, ['creator', 'youtube', 'instagram', 'content', 'reel'])) $roles[] = 'creator';
        if ($this->containsAny($text, ['job', 'resume', 'career', 'skill'])) $roles[] = 'job_seeker';
        if ($this->containsAny($text, ['business', 'startup', 'sales', 'marketing', 'automation'])) $roles[] = 'business';

        return $roles ?: ['creator', 'student'];
    }

    protected function roleScore(string $role, array $roles, int|float $primary, int|float $secondary): int
    {
        $score = (int) round(($primary * 0.65) + ($secondary * 0.35));
        return in_array($role, $roles, true) ? min(100, $score + 14) : $score;
    }

    protected function scoreText(string $text, array $signals): int
    {
        $score = 62;
        foreach ($signals as $signal) {
            if (str_contains(Str::lower($text), $signal)) {
                $score += 8;
            }
        }

        return min(96, $score);
    }

    protected function containsAny(string $text, array $needles): bool
    {
        $text = Str::lower($text);
        foreach ($needles as $needle) {
            if (str_contains($text, Str::lower($needle))) {
                return true;
            }
        }

        return false;
    }
}
