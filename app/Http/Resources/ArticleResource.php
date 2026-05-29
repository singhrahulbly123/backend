<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'title' => $this->title,
            'slug' => $this->slug,
            'status' => $this->status,
            'locale' => $this->locale,
            'content_type' => $this->content_type,
            'excerpt' => $this->excerpt,
            'ai_summary' => $this->ai_summary,
            'key_points' => $this->key_points,
            'body' => $this->when($request->routeIs('*.show') || $request->boolean('full'), $this->body),
            'featured_image' => $this->featured_image,
            'featured_image_optimized' => $this->featured_image ? app(\App\Services\ImageOptimizationService::class)->optimize(url($this->featured_image), 1200, 80) : null,
            'gallery' => $this->gallery,
            'is_breaking' => $this->is_breaking,
            'is_featured' => $this->is_featured,
            'human_reviewed' => $this->human_reviewed,
            'fact_checked' => $this->fact_checked,
            'sources' => $this->sources,
            'timeline' => $this->timeline,
            'faqs' => $this->faqs,
            'reading_time_minutes' => $this->reading_time_minutes,
            'views_count' => $this->views_count,
            'published_at' => $this->published_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'is_fresh' => $this->updated_at?->greaterThan(now()->subDay()),
            'discover_ready' => $this->seoMeta?->discover_optimized ?? false,
            'discover_thumbnail' => $this->seoMeta?->discover_thumbnail,
            'source_references' => $this->sources,
            'quality_score' => $this->quality_score,
            'india_impact_score' => $this->india_impact_score,
            'india_impact_summary' => $this->india_impact_summary,
            'ai_opportunity_score' => $this->ai_opportunity_score,
            'ai_opportunity_summary' => $this->ai_opportunity_summary,
            'audience_roles' => $this->audience_roles,
            'content_quality' => $this->whenLoaded('qualityReport', fn () => [
                'plagiarism_score' => $this->qualityReport->plagiarism_score,
                'hallucination_risk' => $this->qualityReport->hallucination_risk,
                'spam_score' => $this->qualityReport->spam_score,
                'readability_score' => $this->qualityReport->readability_score,
                'seo_score' => $this->qualityReport->seo_score,
                'fact_checks' => $this->qualityReport->fact_checks,
                'recommendations' => $this->qualityReport->recommendations,
            ]),
            'live_updates' => $this->whenLoaded('liveUpdates', fn () => $this->liveUpdates->map(fn ($update) => [
                'id' => $update->id,
                'headline' => $update->headline,
                'content' => $update->content,
                'is_breaking' => $update->is_breaking,
                'published_at' => $update->published_at?->toIso8601String(),
            ])),
            'category' => $this->whenLoaded('category', fn () => [
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),
            'author' => $this->whenLoaded('author', fn () => [
                'name' => $this->author->name,
                'avatar' => $this->author->avatar,
                'bio' => $this->author->bio,
                'is_verified' => $this->author->is_verified_author,
                'designation' => $this->author->designation,
                'slug' => $this->author->authorProfile?->slug,
                'expertise_topics' => $this->author->expertise_topics,
                'social_links' => [
                    'twitter' => $this->author->authorProfile?->twitter,
                    'linkedin' => $this->author->authorProfile?->linkedin,
                ],
            ]),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->pluck('name')),
            'seo' => $this->whenLoaded('seoMeta', fn () => $this->seoMeta),
            'affiliate_links' => AffiliateLinkResource::collection($this->whenLoaded('affiliateLinks')),
        ];
    }
}
