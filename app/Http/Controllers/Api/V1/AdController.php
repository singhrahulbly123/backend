<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AdPlacement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AdController extends Controller
{
    public function placements(Request $request): JsonResponse
    {
        $cacheKey = 'ads:placements:' . md5($request->fullUrl());

        $placements = Cache::remember($cacheKey, 600, function () use ($request) {
            return AdPlacement::where('is_active', true)
                ->when($request->query('slot_key'), fn ($q, $value) => $q->where('slot_key', $value))
                ->when($request->query('page_type'), fn ($q, $value) => $q->where('page_type', $value))
                ->orderByDesc('priority')
                ->get([
                    'slot_key',
                    'page_type',
                    'ad_format',
                    'reserved_width',
                    'reserved_height',
                    'revenue_channel',
                    'ad_code',
                    'lazy_load',
                ]);
        });

        return response()->json(['data' => $placements]);
    }
}
