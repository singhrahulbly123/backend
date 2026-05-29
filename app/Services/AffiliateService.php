<?php

namespace App\Services;

use App\Models\AffiliateLink;
use App\Models\AnalyticsEvent;
use Illuminate\Http\Request;

class AffiliateService
{
    protected AnalyticsService $analytics;

    public function __construct(AnalyticsService $analytics)
    {
        $this->analytics = $analytics;
    }

    /**
     * Record a click for an affiliate link and track analytics.
     */
    public function recordClick(AffiliateLink $link, Request $request): array
    {
        $link->increment('clicks_count');

        $this->analytics->track([
            'event_type' => 'affiliate_click',
            'trackable_type' => AffiliateLink::class,
            'trackable_id' => $link->id,
            'metadata' => [
                'slug' => $link->slug,
                'category' => $link->category,
                'network' => $link->network,
                'utm_source' => $request->query('utm_source'),
                'placement' => $request->input('placement') ?? $request->query('placement'),
                'article_id' => $request->input('article_id') ?? $request->query('article_id'),
                'article_slug' => $request->input('article_slug') ?? $request->query('article_slug'),
                'source_type' => $request->input('source_type') ?? $request->query('source_type'),
                'source_id' => $request->input('source_id') ?? $request->query('source_id'),
            ],
        ], $request);

        return [
            'redirect' => $link->destination_url,
            'clicks_count' => $link->clicks_count,
        ];
    }

    public function calculateCommission(AffiliateLink $link, float $revenue = 0.0): array
    {
        $rate = max(0.0, min(1.0, (float) ($link->commission_rate ?? 0.05)));
        $estimated = max(0.0, $revenue) * $rate;

        return [
            'estimated_commission_inr' => round($estimated, 2),
            'commission_rate' => $rate,
        ];
    }

    public function recordConversion(AffiliateLink $link, Request $request): array
    {
        $data = $request->validate([
            'revenue_inr' => ['nullable', 'numeric', 'min:0'],
            'order_id' => ['nullable', 'string', 'max:120'],
            'placement' => ['nullable', 'string', 'max:120'],
            'article_id' => ['nullable', 'integer'],
            'article_slug' => ['nullable', 'string', 'max:255'],
            'source_type' => ['nullable', 'string', 'max:120'],
            'source_id' => ['nullable', 'integer'],
        ]);

        $link->increment('conversions_count');
        $revenue = (float) ($data['revenue_inr'] ?? 0);
        $commission = $this->calculateCommission($link, $revenue);

        $this->analytics->track([
            'event_type' => 'affiliate_conversion',
            'trackable_type' => AffiliateLink::class,
            'trackable_id' => $link->id,
            'revenue_inr' => $revenue,
            'metadata' => [
                ...$data,
                'slug' => $link->slug,
                'category' => $link->category,
                'network' => $link->network,
                'estimated_commission_inr' => $commission['estimated_commission_inr'],
            ],
        ], $request);

        return [
            'tracked' => true,
            'conversions_count' => $link->conversions_count,
            ...$commission,
        ];
    }

    public function performanceSummary(): array
    {
        $links = AffiliateLink::query()
            ->orderByDesc('clicks_count')
            ->limit(20)
            ->get();

        $linkIds = $links->pluck('id')->all();
        $events = AnalyticsEvent::whereIn('event_type', [
                'affiliate_click',
                'affiliate_cta_click',
                'affiliate_cta_impression',
                'affiliate_conversion',
                'ai_tool_cta_click',
            ])
            ->where(function ($query) use ($linkIds) {
                $query->whereIn('trackable_id', $linkIds)
                    ->orWhere('event_type', 'ai_tool_cta_click');
            })
            ->get();

        $totalClicks = (int) $events->whereIn('event_type', ['affiliate_click', 'affiliate_cta_click', 'ai_tool_cta_click'])->count();
        $totalImpressions = (int) $events->where('event_type', 'affiliate_cta_impression')->count();
        $totalConversions = (int) $links->sum('conversions_count');
        $revenue = (float) $events->where('event_type', 'affiliate_conversion')->sum('revenue_inr');
        $pageViews = max(1, AnalyticsEvent::where('event_type', 'page_view')->count());
        $placementAttribution = $events
            ->filter(fn (AnalyticsEvent $event) => in_array($event->event_type, ['affiliate_click', 'affiliate_cta_click', 'affiliate_cta_impression', 'affiliate_conversion'], true))
            ->groupBy(fn (AnalyticsEvent $event) => (string) ($event->metadata['placement'] ?? 'unknown'))
            ->map(function ($group, string $placement) {
                $impressions = $group->where('event_type', 'affiliate_cta_impression')->count();
                $clicks = $group->whereIn('event_type', ['affiliate_click', 'affiliate_cta_click'])->count();
                $revenue = (float) $group->where('event_type', 'affiliate_conversion')->sum('revenue_inr');

                return [
                    'placement' => $placement,
                    'impressions' => $impressions,
                    'clicks' => $clicks,
                    'ctr' => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : 0,
                    'revenue_inr' => round($revenue, 2),
                ];
            })
            ->sortByDesc('clicks')
            ->values();

        return [
            'total_clicks' => $totalClicks,
            'total_impressions' => $totalImpressions,
            'total_conversions' => $totalConversions,
            'conversion_rate' => $totalClicks > 0 ? round(($totalConversions / $totalClicks) * 100, 2) : 0,
            'estimated_revenue_inr' => round($revenue, 2),
            'affiliate_rpm' => round($revenue / ($pageViews / 1000), 2),
            'placement_attribution' => $placementAttribution,
            'top_links' => $links->map(function (AffiliateLink $link) {
                $linkRevenue = (float) AnalyticsEvent::where('event_type', 'affiliate_conversion')
                    ->where('trackable_type', AffiliateLink::class)
                    ->where('trackable_id', $link->id)
                    ->sum('revenue_inr');

                return [
                    'id' => $link->id,
                    'name' => $link->name,
                    'slug' => $link->slug,
                    'category' => $link->category,
                    'network' => $link->network,
                    'clicks' => (int) $link->clicks_count,
                    'conversions' => (int) $link->conversions_count,
                    'commission_rate' => (float) $link->commission_rate,
                    'estimated_commission_inr' => $this->calculateCommission($link, $linkRevenue)['estimated_commission_inr'],
                ];
            })->values(),
        ];
    }
}
