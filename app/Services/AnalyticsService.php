<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use App\Models\Article;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AnalyticsService
{
    public function track(array $data, ?Request $request = null): AnalyticsEvent
    {
        $sessionId = $request?->header('X-Session-Id') ?? (string) Str::uuid();

        $record = AnalyticsEvent::create([
            ...$data,
            'session_id' => $sessionId,
            'user_agent' => isset($request) ? Str::limit($request->userAgent() ?? '', 255) : null,
            'ip_hash' => isset($request) ? hash('sha256', $request->ip().config('app.key')) : null,
            'referrer' => isset($request) ? Str::limit($request->header('Referer') ?? '', 500) : null,
            'recorded_at' => now(),
        ]);

        return $record;
    }

    public function discoverSummary(?int $articleId = null): array
    {
        $query = AnalyticsEvent::whereIn('event_type', ['discover_impression', 'discover_click']);
        if ($articleId) {
            $query->where('trackable_type', Article::class)
                ->where('trackable_id', $articleId);
        }

        $impressions = (clone $query)->where('event_type', 'discover_impression')->count();
        $clicks = (clone $query)->where('event_type', 'discover_click')->count();
        $ctr = $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0;

        return [
            'impressions' => $impressions,
            'clicks' => $clicks,
            'ctr' => $ctr,
            'top_articles' => $this->topDiscoverArticles(),
            'rankings' => $this->discoverRankings(),
        ];
    }

    public function discoverRankings(int $limit = 20): array
    {
        $rows = AnalyticsEvent::selectRaw("
                trackable_id,
                sum(case when event_type = 'discover_impression' then 1 else 0 end) as impressions,
                sum(case when event_type = 'discover_click' then 1 else 0 end) as clicks
            ")
            ->where('trackable_type', Article::class)
            ->whereIn('event_type', ['discover_impression', 'discover_click'])
            ->groupBy('trackable_id')
            ->orderByDesc('clicks')
            ->orderByDesc('impressions')
            ->limit($limit)
            ->get();

        $articles = Article::whereIn('id', $rows->pluck('trackable_id')->all())
            ->get(['id', 'title', 'slug', 'views_count', 'published_at'])
            ->keyBy('id');

        return $rows->values()->map(function ($row, $index) use ($articles) {
            $article = $articles[(int) $row->trackable_id] ?? null;
            $impressions = (int) $row->impressions;
            $clicks = (int) $row->clicks;

            return [
                'position' => $index + 1,
                'article_id' => (int) $row->trackable_id,
                'title' => $article?->title,
                'slug' => $article?->slug,
                'views' => (int) ($article?->views_count ?? 0),
                'impressions' => $impressions,
                'clicks' => $clicks,
                'ctr' => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0,
                'published_at' => $article?->published_at,
            ];
        })->toArray();
    }

    protected function topDiscoverArticles(): array
    {
        $rows = AnalyticsEvent::selectRaw('trackable_id, count(*) as event_count')
            ->where('trackable_type', Article::class)
            ->whereIn('event_type', ['discover_impression', 'discover_click'])
            ->groupBy('trackable_id')
            ->orderByDesc('event_count')
            ->limit(5)
            ->get();

        $titles = Article::whereIn('id', $rows->pluck('trackable_id')->all())
            ->pluck('title', 'id');

        return $rows->values()->map(function ($row, $index) use ($titles) {
            return [
                'position' => $index + 1,
                'article_id' => $row->trackable_id,
                'title' => $titles[$row->trackable_id] ?? null,
                'events' => (int) $row->event_count,
            ];
        })->toArray();
    }
}
