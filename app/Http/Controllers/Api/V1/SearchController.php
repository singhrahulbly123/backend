<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use App\Services\Search\MeilisearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(private readonly MeilisearchService $search) {}

    public function search(Request $request): JsonResponse
    {
        $request->validate(['q' => ['required', 'string', 'min:2']]);

        try {
            $hits = $this->search->search($request->q, $request->locale);
            $ids = collect($hits)->pluck('id');
            $articles = Article::whereIn('id', $ids)->published()->get()->sortBy(fn ($a) => $ids->search($a->id))->values();
        } catch (\Throwable) {
            $articles = Article::published()
                ->where('title', 'ilike', '%'.$request->q.'%')
                ->orWhere('excerpt', 'ilike', '%'.$request->q.'%')
                ->limit(20)
                ->get();
        }

        return ArticleResource::collection($articles)->response();
    }

    public function suggest(Request $request): JsonResponse
    {
        $q = $request->get('q', '');
        if (strlen($q) < 2) {
            return response()->json(['suggestions' => []]);
        }

        $suggestions = Article::published()
            ->where('title', 'ilike', "{$q}%")
            ->limit(8)
            ->pluck('title');

        return response()->json(['suggestions' => $suggestions]);
    }
}
