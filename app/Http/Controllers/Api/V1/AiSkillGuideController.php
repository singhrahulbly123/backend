<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AiSkillGuide;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiSkillGuideController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $guides = AiSkillGuide::published()
            ->when($request->category, fn ($query, $category) => $query->where('category', $category))
            ->when($request->career_stage, fn ($query, $stage) => $query->where('career_stage', $stage))
            ->orderByDesc('is_featured')
            ->latest('published_at')
            ->paginate((int) $request->get('per_page', 18));

        return response()->json($guides);
    }

    public function show(string $slug): JsonResponse
    {
        $guide = AiSkillGuide::published()->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => $guide]);
    }
}
