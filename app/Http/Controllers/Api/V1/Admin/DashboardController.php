<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use App\Models\Article;
use App\Models\TrendingTopic;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(): JsonResponse
    {
        $today = now()->startOfDay();

        return response()->json([
            'stats' => [
                'articles_published' => Article::where('status', 'published')->count(),
                'articles_draft' => Article::where('status', 'draft')->count(),
                'views_today' => AnalyticsEvent::where('event_type', 'page_view')
                    ->where('recorded_at', '>=', $today)->count(),
                'trending_topics' => TrendingTopic::where('status', 'detected')->count(),
                'editors_online' => User::where('last_login_at', '>=', now()->subHours(24))->count(),
            ],
            'top_articles' => Article::published()->orderByDesc('views_count')->limit(5)->get(['title', 'slug', 'views_count']),
            'rpm_estimate' => AnalyticsEvent::where('recorded_at', '>=', $today->copy()->subDays(7))
                ->whereNotNull('revenue_inr')
                ->avg('revenue_inr'),
            'traffic_by_day' => AnalyticsEvent::select(DB::raw('DATE(recorded_at) as day'), DB::raw('count(*) as views'))
                ->where('event_type', 'page_view')
                ->where('recorded_at', '>=', now()->subDays(7))
                ->groupBy('day')
                ->orderBy('day')
                ->get(),
        ]);
    }
}
