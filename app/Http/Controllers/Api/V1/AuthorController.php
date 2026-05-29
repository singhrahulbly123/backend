<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\AuthorResource;
use App\Models\AuthorProfile;
use App\Models\Article;
use Illuminate\Http\JsonResponse;

class AuthorController extends Controller
{
    public function show(string $slug): JsonResponse
    {
        $profile = AuthorProfile::with('user')->where('slug', $slug)->firstOrFail();

        $articles = Article::published()
            ->where('author_id', $profile->user_id)
            ->with(['category', 'author.authorProfile', 'seoMeta'])
            ->latest('published_at')
            ->limit(12)
            ->get();

        return response()->json([
            'data' => new AuthorResource($profile),
            'articles' => ArticleResource::collection($articles),
        ]);
    }
}
