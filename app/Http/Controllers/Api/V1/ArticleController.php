<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Models\AnalyticsEvent;
use App\Models\Article;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ArticleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cacheKey = 'articles:index:'.md5($request->fullUrl());

        $articles = Cache::remember($cacheKey, 30, function () use ($request) {
            $locale = $request->get('locale', 'en');

            return Article::published()
                ->with(['category', 'author.authorProfile', 'tags', 'seoMeta'])
                ->where('locale', $locale)
                ->when($request->category, fn ($q, $s) => $q->whereHas('category', fn ($c) => $c->where('slug', $s)))
                ->when($request->type, fn ($q, $t) => $q->where('content_type', $t))
                ->latest('published_at')
                ->paginate(min((int) $request->get('per_page', 20), 50));
        });

        return ArticleResource::collection($articles)->response();
    }

    public function featured(): JsonResponse
    {
        $articles = Cache::remember('articles:featured:en', 60, fn () => Article::published()
            ->where('locale', 'en')
            ->where('is_featured', true)
            ->with(['category', 'author'])
            ->latest('published_at')
            ->limit(12)
            ->get());

        return ArticleResource::collection($articles)->response();
    }

    public function viral(): JsonResponse
    {
        $articles = Cache::remember('articles:viral:en', 60, fn () => Article::published()
            ->where('locale', 'en')
            ->orderByDesc('views_count')
            ->with(['category'])
            ->limit(16)
            ->get());

        return ArticleResource::collection($articles)->response();
    }

    public function show(string $slug): JsonResponse
    {
        $article = Cache::remember("articles:show:{$slug}", 60, fn () => Article::published()
            ->where('slug', $slug)
            ->with(['category', 'author.authorProfile', 'tags', 'seoMeta', 'qualityReport', 'affiliateLinks', 'liveUpdates', 'comments' => fn ($q) => $q->where('status', 'approved')->latest()])
            ->firstOrFail());

        $related = Article::published()
            ->where('locale', 'en')
            ->where('category_id', $article->category_id)
            ->where('id', '!=', $article->id)
            ->with(['category', 'author.authorProfile', 'seoMeta', 'qualityReport'])
            ->limit(6)
            ->get();

        return response()->json([
            'data' => new ArticleResource($article),
            'related' => ArticleResource::collection($related),
        ]);
    }

    public function recordView(string $slug, Request $request): JsonResponse
    {
        $article = Article::where('slug', $slug)->firstOrFail();
        $article->increment('views_count');

        AnalyticsEvent::create([
            'event_type' => 'page_view',
            'trackable_type' => Article::class,
            'trackable_id' => $article->id,
            'session_id' => $request->header('X-Session-Id'),
            'referrer' => $request->header('Referer'),
            'recorded_at' => now(),
        ]);

        if (str_contains($request->query('utm_source', ''), 'discover') || str_contains(strtolower($request->header('Referer') ?? ''), 'google.com')) {
            AnalyticsEvent::create([
                'event_type' => 'discover_impression',
                'trackable_type' => Article::class,
                'trackable_id' => $article->id,
                'session_id' => $request->header('X-Session-Id'),
                'referrer' => $request->header('Referer'),
                'metadata' => ['source' => 'discover'],
                'recorded_at' => now(),
            ]);
        }

        return response()->json(['views' => $article->fresh()->views_count]);
    }
}
