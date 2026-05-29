<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiSkillGuide;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AiSkillGuideAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(AiSkillGuide::query()->latest()->paginate((int) $request->get('per_page', 50)));
    }

    public function store(Request $request): JsonResponse
    {
        $guide = AiSkillGuide::create($this->data($request));

        return response()->json(['data' => $guide], 201);
    }

    public function update(Request $request, AiSkillGuide $aiSkillGuide): JsonResponse
    {
        $aiSkillGuide->update($this->data($request, true));

        return response()->json(['data' => $aiSkillGuide->fresh()]);
    }

    protected function data(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $data = $request->validate([
            'title' => [$required, 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'category' => [$required, 'string', 'max:100'],
            'career_stage' => ['nullable', 'string', 'max:100'],
            'summary' => ['nullable', 'string'],
            'body' => ['nullable', 'string'],
            'skills' => ['nullable', 'array'],
            'tools' => ['nullable', 'array'],
            'projects' => ['nullable', 'array'],
            'roadmap' => ['nullable', 'array'],
            'faqs' => ['nullable', 'array'],
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
