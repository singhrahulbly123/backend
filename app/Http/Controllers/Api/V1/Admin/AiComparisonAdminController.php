<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiComparison;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AiComparisonAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(AiComparison::query()->latest()->paginate((int) $request->get('per_page', 50)));
    }

    public function store(Request $request): JsonResponse
    {
        $comparison = AiComparison::create($this->data($request));

        return response()->json(['data' => $comparison], 201);
    }

    public function update(Request $request, AiComparison $comparison): JsonResponse
    {
        $comparison->update($this->data($request, true));

        return response()->json(['data' => $comparison->fresh()]);
    }

    public function destroy(AiComparison $comparison): JsonResponse
    {
        $comparison->delete();

        return response()->json(['message' => 'Comparison deleted.']);
    }

    protected function data(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $data = $request->validate([
            'title' => [$required, 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'category' => [$required, 'string', 'max:100'],
            'tool_a' => [$required, 'string', 'max:150'],
            'tool_b' => [$required, 'string', 'max:150'],
            'summary' => ['nullable', 'string'],
            'winner' => ['nullable', 'string', 'max:150'],
            'best_for' => ['nullable', 'array'],
            'scorecard' => ['nullable', 'array'],
            'pros_cons' => ['nullable', 'array'],
            'faqs' => ['nullable', 'array'],
            'cta_label' => ['nullable', 'string', 'max:255'],
            'cta_url' => ['nullable', 'string', 'max:1000'],
            'is_featured' => ['boolean'],
            'status' => ['nullable', 'in:draft,published,archived'],
            'published_at' => ['nullable', 'date'],
        ]);

        if (empty($data['slug']) && ! empty($data['title'])) {
            $data['slug'] = Str::slug($data['title']).'-'.Str::random(5);
        }

        return $data;
    }
}
