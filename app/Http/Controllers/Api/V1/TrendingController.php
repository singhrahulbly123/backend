<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use App\Models\TrendingTopic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TrendingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $articles = Cache::remember('trending:articles:'.($request->locale ?? 'en'), 60, fn () => Article::published()
            ->when($request->locale, fn ($q, $l) => $q->where('locale', $l))
            ->orderByDesc('views_count')
            ->limit(12)
            ->with(['category'])
            ->get());

        return ArticleResource::collection($articles)->response();
    }

    public function topics(): JsonResponse
    {
        $topics = TrendingTopic::where('status', 'detected')
            ->orderByDesc('trend_score')
            ->limit(20)
            ->get();

        return response()->json(['data' => $topics]);
    }
}
