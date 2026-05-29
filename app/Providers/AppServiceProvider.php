<?php

namespace App\Providers;

use App\Models\Article;
use App\Observers\ArticleObserver;
use App\Services\AI\AiOrchestrator;
use App\Services\AI\GeminiProvider;
use App\Services\AI\GroqProvider;
use App\Services\AI\NewsAiService;
use App\Services\AI\OpenAiProvider;
use App\Services\HeadlineExperimentService;
use App\Services\HeadlineScorerService;
use App\Services\ImageConversionService;
use App\Services\Search\MeilisearchService;
use App\Services\SEO\SchemaBuilder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OpenAiProvider::class);
        $this->app->singleton(GeminiProvider::class);
        $this->app->singleton(GroqProvider::class);
        $this->app->singleton(AiOrchestrator::class);
        $this->app->singleton(NewsAiService::class);
        $this->app->singleton(\App\Services\AI\ResponseValidator::class);
        $this->app->singleton(\App\Services\Trends\GoogleTrendsService::class);
        $this->app->singleton(\App\Services\Trends\TwitterTrendsService::class);
        $this->app->singleton(\App\Services\Trends\YouTubeTrendsService::class);
        $this->app->singleton(SchemaBuilder::class);
        $this->app->singleton(\App\Services\SEO\SeoEngineService::class);
        $this->app->singleton(MeilisearchService::class);
        $this->app->singleton(HeadlineScorerService::class);
        $this->app->singleton(\App\Services\AnalyticsService::class);
        $this->app->singleton(\App\Services\WebStoriesService::class);
        $this->app->singleton(\App\Services\AffiliateService::class);
        $this->app->singleton(\App\Services\TtsService::class);
        $this->app->singleton(\App\Services\CdnInvalidationService::class);
        $this->app->singleton(\App\Services\ImageOptimizationService::class);
        $this->app->singleton(HeadlineExperimentService::class);
        $this->app->singleton(ImageConversionService::class);
        $this->app->singleton(\App\Services\DiscoverThumbnailService::class);
        $this->app->singleton(\App\Services\DiscoverOptimizationService::class);
        $this->app->singleton(\App\Services\AI\ProviderRateLimiter::class);
        $this->app->singleton(\App\Services\AI\BillingService::class);
        $this->app->singleton(\App\Services\SecretsManager::class);
    }

    public function boot(): void
    {
        Article::observe(ArticleObserver::class);

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });
    }
}
