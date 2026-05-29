<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AiComparison;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiComparisonController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $comparisons = AiComparison::published()
            ->when($request->category, fn ($query, $category) => $query->where('category', $category))
            ->when($request->q, fn ($query, $q) => $query->where(fn ($inner) => $inner
                ->where('title', 'like', "%{$q}%")
                ->orWhere('tool_a', 'like', "%{$q}%")
                ->orWhere('tool_b', 'like', "%{$q}%")
                ->orWhere('summary', 'like', "%{$q}%")
            ))
            ->orderByDesc('is_featured')
            ->latest('published_at')
            ->paginate((int) $request->get('per_page', 18));

        return response()->json($comparisons);
    }

    public function show(string $slug): JsonResponse
    {
        $comparison = AiComparison::published()->where('slug', $slug)->firstOrFail();

        $related = AiComparison::published()
            ->where('id', '!=', $comparison->id)
            ->where('category', $comparison->category)
            ->limit(6)
            ->get();

        return response()->json(['data' => $comparison, 'related' => $related]);
    }
}
