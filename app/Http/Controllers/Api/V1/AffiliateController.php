<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AffiliateLink;
use App\Services\AffiliateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AffiliateController extends Controller
{
    protected AffiliateService $service;

    public function __construct(AffiliateService $service)
    {
        $this->service = $service;
    }

    public function show(string $slug): JsonResponse
    {
        $link = AffiliateLink::where('slug', $slug)->where('is_active', true)->firstOrFail();

        return response()->json(['data' => $link]);
    }

    public function trackClick(string $slug, Request $request): JsonResponse
    {
        $link = AffiliateLink::where('slug', $slug)->firstOrFail();

        $result = $this->service->recordClick($link, $request);

        return response()->json($result);
    }

    public function trackConversion(string $slug, Request $request): JsonResponse
    {
        $link = AffiliateLink::where('slug', $slug)->firstOrFail();

        return response()->json($this->service->recordConversion($link, $request));
    }
}
