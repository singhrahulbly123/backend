<?php

namespace App\Services\SEO;

use App\Models\Article;
use App\Models\Category;
use App\Services\ImageOptimizationService;

class SchemaBuilder
{
    protected ImageOptimizationService $imageOptimization;

    public function __construct(ImageOptimizationService $imageOptimization)
    {
        $this->imageOptimization = $imageOptimization;
    }

    public function newsArticle(Article $article): array
    {
        $author = $article->author;

        $imageUrl = $article->featured_image ? $this->imageOptimization->optimize(url($article->featured_image), 1200, 80) : null;

        return [
            '@context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'headline' => $article->title,
            'description' => $article->excerpt ?? $article->ai_summary,
            'image' => $imageUrl ? [[
                '@type' => 'ImageObject',
                'url' => $imageUrl,
                'width' => 1200,
                'height' => 675,
            ]] : [],
            'datePublished' => $article->published_at?->toIso8601String(),
            'dateModified' => $article->updated_at->toIso8601String(),
            'author' => [
                '@type' => 'Person',
                'name' => $author?->name ?? 'Global AI News',
                'url' => $author ? url("/author/{$author->authorProfile?->slug}") : url('/'),
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => config('app.name', 'Global AI News'),
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => url('/logo.png'),
                ],
            ],
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $article->canonical_url ?? url("/news/{$article->slug}"),
            ],
            'inLanguage' => $article->locale,
            'isAccessibleForFree' => true,
        ];
    }

    public function breadcrumb(Article $article): array
    {
        $items = [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => url('/')],
        ];

        if ($article->category) {
            $items[] = [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => $article->category->name,
                'item' => url("/category/{$article->category->slug}"),
            ];
        }

        $items[] = [
            '@type' => 'ListItem',
            'position' => count($items) + 1,
            'name' => $article->title,
            'item' => url("/news/{$article->slug}"),
        ];

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    public function faq(Article $article): ?array
    {
        if (empty($article->faqs)) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => collect($article->faqs)->map(fn ($faq) => [
                '@type' => 'Question',
                'name' => $faq['question'] ?? '',
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['answer'] ?? '',
                ],
            ])->values()->all(),
        ];
    }

    public function fullGraph(Article $article): array
    {
        $graph = [
            $this->newsArticle($article),
            $this->breadcrumb($article),
        ];

        if ($faq = $this->faq($article)) {
            $graph[] = $faq;
        }

        return [
            '@context' => 'https://schema.org',
            '@graph' => $graph,
        ];
    }
}
