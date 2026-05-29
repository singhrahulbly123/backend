<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\LearningLesson;
use App\Models\LearningPath;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LearningAdminController extends Controller
{
    public function paths(Request $request): JsonResponse
    {
        return response()->json(LearningPath::withCount('lessons')->latest()->paginate((int) $request->get('per_page', 50)));
    }

    public function storePath(Request $request): JsonResponse
    {
        $path = LearningPath::create($this->pathData($request));

        return response()->json(['data' => $path], 201);
    }

    public function updatePath(Request $request, LearningPath $learningPath): JsonResponse
    {
        $learningPath->update($this->pathData($request, true));

        return response()->json(['data' => $learningPath->fresh()]);
    }

    public function lessons(Request $request): JsonResponse
    {
        $lessons = LearningLesson::with('learningPath:id,title')
            ->when($request->learning_path_id, fn ($query, $id) => $query->where('learning_path_id', $id))
            ->orderBy('sort_order')
            ->latest()
            ->paginate((int) $request->get('per_page', 50));

        return response()->json($lessons);
    }

    public function storeLesson(Request $request): JsonResponse
    {
        $lesson = LearningLesson::create($this->lessonData($request));

        return response()->json(['data' => $lesson], 201);
    }

    public function updateLesson(Request $request, LearningLesson $learningLesson): JsonResponse
    {
        $learningLesson->update($this->lessonData($request, true));

        return response()->json(['data' => $learningLesson->fresh()]);
    }

    protected function pathData(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $data = $request->validate([
            'title' => [$required, 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'category' => [$required, 'string', 'max:100'],
            'level' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'outcomes' => ['nullable', 'array'],
            'audience' => ['nullable', 'array'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'is_featured' => ['boolean'],
            'status' => ['nullable', 'in:draft,published,archived'],
            'published_at' => ['nullable', 'date'],
        ]);

        if (empty($data['slug']) && ! empty($data['title'])) {
            $data['slug'] = Str::slug($data['title']).'-'.Str::random(5);
        }

        return $data;
    }

    protected function lessonData(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $data = $request->validate([
            'learning_path_id' => [$required, 'exists:learning_paths,id'],
            'title' => [$required, 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string'],
            'content' => [$required, 'string'],
            'action_steps' => ['nullable', 'array'],
            'resources' => ['nullable', 'array'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'is_free' => ['boolean'],
            'status' => ['nullable', 'in:draft,published,archived'],
            'published_at' => ['nullable', 'date'],
        ]);

        if (empty($data['slug']) && ! empty($data['title'])) {
            $data['slug'] = Str::slug($data['title']).'-'.Str::random(5);
        }

        return $data;
    }
}
