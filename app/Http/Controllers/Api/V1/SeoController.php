<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use App\Models\WebStory;
use App\Services\SEO\SchemaBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function __construct(private readonly SchemaBuilder $schema) {}

    public function sitemap(): Response
    {
        $articles = Article::published()->select(['slug', 'updated_at', 'locale'])->get();
        $categories = Category::where('is_active', true)->select(['slug', 'updated_at'])->get();
        $stories = WebStory::where('status', 'published')->select(['slug', 'updated_at'])->get();

        $xml = view('seo.sitemap', compact('articles', 'categories', 'stories'))->render();

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function articleSchema(string $slug): JsonResponse
    {
        $article = Article::published()->where('slug', $slug)->firstOrFail();

        return response()->json(['schema' => $this->schema->fullGraph($article)]);
    }
}
