<?php

namespace App\Observers;

use App\Models\Article;
use App\Services\CdnInvalidationService;
use Illuminate\Support\Facades\Cache;

class ArticleObserver
{
    public function __construct(private readonly CdnInvalidationService $cdn) {}

    public function saved(Article $article): void
    {
        if ($article->status !== 'published') {
            return;
        }

        Cache::forget("articles:show:{$article->slug}");
        $this->cdn->purgeArticle($article);
    }

    public function deleted(Article $article): void
    {
        Cache::forget("articles:show:{$article->slug}");
        $this->cdn->purgeArticle($article);
    }
}
