<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\WebStory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class WebStoryController extends Controller
{
    public function index(): JsonResponse
    {
        $stories = Cache::remember('web_stories:index', 120, fn () => WebStory::where('status', 'published')
            ->latest('published_at')
            ->limit(20)
            ->get(['id', 'title', 'slug', 'cover_image', 'locale', 'published_at']));

        return response()->json(['data' => $stories]);
    }

    public function show(string $slug): JsonResponse
    {
        $story = WebStory::where('slug', $slug)->where('status', 'published')->firstOrFail();

        return response()->json(['data' => $story]);
    }
}
