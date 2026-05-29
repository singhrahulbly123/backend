<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    protected AnalyticsService $analytics;

    public function __construct(AnalyticsService $analytics)
    {
        $this->analytics = $analytics;
    }

    public function track(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_type' => ['required', 'string', 'max:64'],
            'trackable_type' => ['nullable', 'string'],
            'trackable_id' => ['nullable', 'integer'],
            'metadata' => ['nullable', 'array'],
            'revenue_inr' => ['nullable', 'numeric'],
            'utm_source' => ['nullable', 'string'],
            'utm_medium' => ['nullable', 'string'],
            'utm_campaign' => ['nullable', 'string'],
        ]);

        $this->analytics->track($data, $request);

        return response()->json(['tracked' => true]);
    }

    public function discoverSummary(Request $request): JsonResponse
    {
        $articleId = $request->query('article_id');
        $summary = $this->analytics->discoverSummary($articleId ? (int) $articleId : null);

        return response()->json(['data' => $summary]);
    }

    public function affiliateSummary(\App\Services\AffiliateService $affiliate): JsonResponse
    {
        return response()->json(['data' => $affiliate->performanceSummary()]);
    }
}
