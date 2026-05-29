<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LearningLesson;
use App\Models\LearningPath;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LearningController extends Controller
{
    public function paths(Request $request): JsonResponse
    {
        $paths = LearningPath::published()
            ->withCount(['lessons' => fn ($query) => $query->published()])
            ->when($request->category, fn ($query, $category) => $query->where('category', $category))
            ->when($request->level, fn ($query, $level) => $query->where('level', $level))
            ->orderByDesc('is_featured')
            ->latest('published_at')
            ->paginate((int) $request->get('per_page', 18));

        return response()->json($paths);
    }

    public function path(string $slug): JsonResponse
    {
        $path = LearningPath::published()
            ->with(['lessons' => fn ($query) => $query->published()->orderBy('sort_order')])
            ->where('slug', $slug)
            ->firstOrFail();

        return response()->json(['data' => $path]);
    }

    public function lesson(string $pathSlug, string $lessonSlug): JsonResponse
    {
        $path = LearningPath::published()->where('slug', $pathSlug)->firstOrFail();
        $lesson = LearningLesson::published()
            ->where('learning_path_id', $path->id)
            ->where('slug', $lessonSlug)
            ->firstOrFail();

        $next = LearningLesson::published()
            ->where('learning_path_id', $path->id)
            ->where('sort_order', '>', $lesson->sort_order)
            ->orderBy('sort_order')
            ->first();

        return response()->json(['data' => $lesson, 'path' => $path, 'next' => $next]);
    }
}
