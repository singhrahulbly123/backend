<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use App\Models\LiveUpdate;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class BreakingNewsController extends Controller
{
    public function index(): JsonResponse
    {
        $breaking = Cache::remember('breaking:articles', 15, fn () => Article::published()
            ->where('is_breaking', true)
            ->latest('published_at')
            ->limit(5)
            ->get());

        $ticker = Cache::remember('breaking:ticker', 10, fn () => LiveUpdate::where('is_breaking', true)
            ->latest('published_at')
            ->limit(10)
            ->get(['id', 'headline', 'published_at', 'article_id']));

        return response()->json([
            'articles' => ArticleResource::collection($breaking),
            'ticker' => $ticker,
            'pulse' => Redis::get('trending:pulse') ?: 'stable',
        ]);
    }
}
