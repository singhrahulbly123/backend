<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\HeadlineExperiment;
use App\Models\HeadlineVariant;
use App\Services\AnalyticsService;
use App\Services\HeadlineExperimentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HeadlineExperimentController extends Controller
{
    public function __construct(private readonly HeadlineExperimentService $experiments, private readonly AnalyticsService $analytics)
    {
    }

    public function index(Article $article): JsonResponse
    {
        $experiments = HeadlineExperiment::where('article_id', $article->id)
            ->with(['variants', 'winner'])
            ->latest()
            ->get();

        return response()->json(['data' => $experiments]);
    }

    public function store(Request $request, Article $article): JsonResponse
    {
        $data = $request->validate([
            'headlines' => ['required', 'array', 'min:2'],
            'headlines.*' => ['required', 'string', 'max:500'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'locale' => ['nullable', 'string', 'max:10'],
        ]);

        $experiment = $this->experiments->createExperiment($article, $data['headlines'], [
            'title' => $data['title'] ?? 'Google Discover headline test',
            'description' => $data['description'] ?? null,
            'locale' => $data['locale'] ?? $article->locale ?? 'en',
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $experiment]);
    }

    public function show(HeadlineExperiment $headlineExperiment): JsonResponse
    {
        return response()->json(['data' => $headlineExperiment->load(['variants', 'winner', 'article'])]);
    }

    public function recordVariantEvent(Request $request, HeadlineExperiment $headlineExperiment, HeadlineVariant $variant): JsonResponse
    {
        $data = $request->validate([
            'event_type' => ['required', 'in:impression,click'],
        ]);

        if ($variant->headline_experiment_id !== $headlineExperiment->id) {
            return response()->json(['message' => 'Variant does not belong to this experiment.'], 404);
        }

        $updated = $this->experiments->recordVariantEvent($variant, $data['event_type']);

        $this->analytics->track([
            'event_type' => "headline_variant_{$data['event_type']}",
            'trackable_type' => HeadlineVariant::class,
            'trackable_id' => $variant->id,
            'metadata' => [
                'experiment_id' => $headlineExperiment->id,
                'article_id' => $headlineExperiment->article_id,
                'headline' => $variant->headline,
            ],
        ], $request);

        return response()->json(['data' => $updated]);
    }

    public function finalize(Request $request, HeadlineExperiment $headlineExperiment): JsonResponse
    {
        $data = $request->validate([
            'winner_variant_id' => ['nullable', 'exists:headline_variants,id'],
        ]);

        $winner = null;
        if (! empty($data['winner_variant_id'])) {
            $winner = $headlineExperiment->variants()->findOrFail($data['winner_variant_id']);
        }

        $experiment = $this->experiments->finalizeExperiment($headlineExperiment, $winner);

        return response()->json(['data' => $experiment]);
    }
}
