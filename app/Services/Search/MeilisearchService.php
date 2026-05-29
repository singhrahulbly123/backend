<?php

namespace App\Services\Search;

use App\Models\Article;
use Meilisearch\Client;

class MeilisearchService
{
    private ?Client $client = null;

    public function client(): Client
    {
        if (! $this->client) {
            $this->client = new Client(
                config('meilisearch.host'),
                config('meilisearch.key')
            );
        }

        return $this->client;
    }

    public function index(): \Meilisearch\Endpoints\Indexes
    {
        $index = $this->client()->index(config('meilisearch.index'));

        try {
            $index->fetchRawInfo();
        } catch (\Throwable) {
            $this->client()->createIndex(config('meilisearch.index'), ['primaryKey' => 'id']);
            $index = $this->client()->index(config('meilisearch.index'));
            $index->updateFilterableAttributes(['locale', 'status', 'category_id', 'content_type']);
            $index->updateSortableAttributes(['published_at', 'views_count']);
        }

        return $index;
    }

    public function indexArticle(Article $article): void
    {
        if ($article->status !== 'published') {
            return;
        }

        try {
            $this->index()->addDocuments([[
                'id' => $article->id,
                'title' => $article->title,
                'excerpt' => $article->excerpt,
                'ai_summary' => $article->ai_summary,
                'slug' => $article->slug,
                'locale' => $article->locale,
                'status' => $article->status,
                'category_id' => $article->category_id,
                'content_type' => $article->content_type,
                'published_at' => $article->published_at?->timestamp,
                'views_count' => $article->views_count,
            ]]);
        } catch (\Throwable) {
            // Fail gracefully when Meilisearch is unavailable in local development.
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, ?string $locale = null, int $limit = 20): array
    {
        $filters = ["status = published"];
        if ($locale) {
            $filters[] = "locale = {$locale}";
        }

        try {
            $result = $this->index()->search($query, [
                'limit' => $limit,
                'filter' => implode(' AND ', $filters),
            ]);
            return $result->getHits();
        } catch (\Throwable) {
            return [];
        }
    }
}
