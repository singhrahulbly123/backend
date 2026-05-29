<?php

namespace App\Services;

use App\Models\Article;

class DiscoverOptimizationService
{
    public function __construct(
        private readonly DiscoverThumbnailService $thumbnailService,
        private readonly ImageOptimizationService $imageOptimization
    ) {}

    public function ensureDiscoverAssets(Article $article, array $attributes = []): \App\Models\SeoMeta
    {
        $meta = $article->seoMeta()->first();
        $data = array_merge([
            'discover_optimized' => false,
        ], $attributes);

        $thumbnailUrl = null;
        if ($article->featured_image) {
            $thumbnailUrl = $this->thumbnailService->generateFromFeaturedImage($article->featured_image);
        }

        if ($thumbnailUrl) {
            $data['discover_thumbnail'] = $thumbnailUrl;
            $data['og_image'] = $data['og_image'] ?? $thumbnailUrl;
            $data['discover_optimized'] = true;
        }

        if (($attributes['discover_optimized'] ?? false) && empty($data['og_image']) && $article->featured_image) {
            $data['og_image'] = $this->imageOptimization->optimize(url($article->featured_image), 1200, 80);
        }

        if (! empty($attributes['discover_optimized'])) {
            $data['discover_optimized'] = true;
        }

        return $article->seoMeta()->updateOrCreate([], $data);
    }
}
