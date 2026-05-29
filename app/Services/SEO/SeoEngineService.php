<?php

namespace App\Services\SEO;

use App\Models\Article;
use App\Services\AI\NewsAiService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class SeoEngineService
{
    public function __construct() {}

    /**
     * Optimize article SEO using AI suggestions and decide whether to publish.
     * Returns ['action' => 'publish'|'schedule'|'review', 'slug' => string]
     */
    public function optimizeAndDecidePublish(Article $article, NewsAiService $ai, array $topic = []): array
    {
        try {
            $seo = $ai->optimizeSeo($article);

            // Build slug
            $base = Str::slug($article->title ?: $topic['title'] ?? 'article');
            $slug = $this->uniqueSlug($base, $article->id);

            // Compute simple keyword score from AI keywords vs body frequency
            $keywords = $seo['keywords'] ?? ($seo['keywords'] ?? []);
            $body = mb_strtolower(strip_tags($article->body ?? ''));
            $scores = [];
            foreach ($keywords as $kw) {
                $k = mb_strtolower(trim((string) $kw));
                if ($k === '') continue;
                $scores[] = substr_count($body, $k);
            }
            $avgKeywordFreq = count($scores) ? array_sum($scores)/count($scores) : 0;

            // Persist SEO meta
            $meta = [
                'meta_title' => $seo['meta_title'] ?? $article->title,
                'meta_description' => $seo['meta_description'] ?? ($article->excerpt ?? ''),
                'keywords' => is_array($seo['keywords'] ?? null) ? $seo['keywords'] : [],
                'internal_link_suggestions' => $seo['internal_link_suggestions'] ?? [],
            ];

            $article->update(['slug' => $slug]);
            $article->seoMeta()->updateOrCreate([], $meta);

            // Decision heuristic
            $trendScore = (float) ($topic['trend_score'] ?? 0);
            $seoScore = (float) ($seo['seo_score'] ?? ($avgKeywordFreq * 10));

            if ($trendScore >= 75 && $seoScore >= 60) {
                return ['action' => 'publish', 'slug' => $slug];
            }

            if ($trendScore >= 50 && $seoScore >= 45) {
                // schedule within 1 hour
                return ['action' => 'schedule', 'slug' => $slug, 'delay_minutes' => 60];
            }

            return ['action' => 'review', 'slug' => $slug];
        } catch (\Throwable $e) {
            Log::warning('SeoEngineService failed', ['error' => $e->getMessage()]);
            return ['action' => 'review', 'slug' => Str::slug($article->title ?? 'draft')];
        }
    }

    private function uniqueSlug(string $base, ?int $id = null): string
    {
        $slug = $base;
        $i = 1;
        while (\App\Models\Article::where('slug', $slug)->when($id, fn($q) => $q->where('id', '!=', $id))->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
