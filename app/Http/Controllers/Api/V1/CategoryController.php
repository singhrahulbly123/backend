<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use App\Http\Resources\ArticleResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $locale = $request->get('locale', 'en');
        $categories = Cache::remember('categories:'.$locale, 300, fn () => Category::where('is_active', true)
            ->where('locale', $locale)
            ->orderBy('sort_order')
            ->get());

        return response()->json(['data' => $categories]);
    }

    public function show(string $slug, Request $request): JsonResponse
    {
        $category = Category::where('slug', $slug)->where('locale', 'en')->where('is_active', true)->firstOrFail();

        $articles = Article::published()
            ->where('locale', 'en')
            ->where('category_id', $category->id)
            ->with(['author', 'seoMeta'])
            ->latest('published_at')
            ->paginate(20);

        return response()->json([
            'category' => $category,
            'articles' => ArticleResource::collection($articles),
        ]);
    }
}
