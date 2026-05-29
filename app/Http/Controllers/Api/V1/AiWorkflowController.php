<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use App\Models\ContentQualityReport;
use App\Models\ViralContent;
use App\Services\AI\NewsAiService;
use App\Services\DiscoverOptimizationService;
use App\Services\Search\MeilisearchService;
use App\Services\SEO\SchemaBuilder;
use App\Services\TrendDetectorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiWorkflowController extends Controller
{
    public function __construct(
        private readonly NewsAiService $ai,
        private readonly SchemaBuilder $schema,
        private readonly MeilisearchService $search,
        private readonly TrendDetectorService $trendDetector,
        private readonly DiscoverOptimizationService $discoverOptimization,
    ) {}

    public function generateDraft(Request $request): JsonResponse
    {
        $context = $request->validate([
            'topic' => ['required', 'string'],
            'locale' => ['nullable', 'string'],
            'sources' => ['nullable', 'array'],
            'content_type' => ['nullable', 'string'],
            'tone' => ['nullable', 'string'],
            'save_as_article' => ['nullable', 'boolean'],
            'publish_now' => ['nullable', 'boolean'],
            'status' => ['nullable', 'in:draft,review,scheduled,published,archived'],
            'scheduled_at' => ['nullable', 'date'],
        ]);

        $draft = $this->ai->generateDraft($context, $request->user()->id);

        // If the AI service returned an incomplete draft (e.g. missing body),
        // return a clear 422 response so we don't attempt to persist invalid data.
        if (empty($draft) || empty($draft['body'])) {
            return response()->json([
                'message' => 'Draft generation failed: AI provider returned incomplete draft (missing body). Please check provider keys or try again.',
                'draft' => $draft,
            ], 422);
        }
        $article = null;

        if ($request->boolean('save_as_article') || $request->boolean('publish_now')) {
            $article = $this->createDraftArticle($draft, $context, $request);
        }

        return response()->json([
            'draft' => $draft,
            'article' => $article ? new ArticleResource($article->fresh()->load(['seoMeta'])) : null,
        ]);
    }

    protected function createDraftArticle(array $draft, array $context, Request $request): Article
    {
        $status = 'review';
        if ($request->boolean('publish_now')) {
            $status = 'published';
        } elseif ($request->filled('status')) {
            $status = $request->get('status');
        }

        $article = Article::create([
            'title' => $draft['title'] ?? $context['topic'],
            'excerpt' => $draft['excerpt'] ?? null,
            'ai_summary' => $draft['ai_summary'] ?? null,
            'body' => $draft['body'] ?? null,
            'locale' => $context['locale'] ?? 'en',
            'content_type' => $context['content_type'] ?? 'news',
            'key_points' => $draft['key_points'] ?? [],
            'faqs' => $draft['faqs'] ?? [],
            'sources' => $context['sources'] ?? [],
            'reading_time_minutes' => $draft['reading_time_minutes'] ?? max(3, (int) (str_word_count(strip_tags($draft['body'] ?? '')) / 200)),
            'status' => $status,
            'is_ai_generated' => true,
            'human_reviewed' => false,
            'author_id' => $request->user()->id,
            'scheduled_at' => $request->get('scheduled_at'),
            'published_at' => $request->boolean('publish_now') ? now() : null,
        ]);

        if (! empty($draft['meta_title']) || ! empty($draft['meta_description']) || ! empty($draft['keywords'])) {
            $article->seoMeta()->updateOrCreate([], [
                'meta_title' => $draft['meta_title'] ?? $article->title,
                'meta_description' => $draft['meta_description'] ?? $article->excerpt,
                'keywords' => is_array($draft['keywords'] ?? null) ? $draft['keywords'] : [],
                'discover_optimized' => false,
            ]);
        }

        if ($article->status === 'published') {
            $this->discoverOptimization->ensureDiscoverAssets($article);
        }

        return $article;
    }

    public function optimizeSeo(Request $request): JsonResponse
    {
        $article = Article::findOrFail($request->validate(['article_id' => ['required', 'exists:articles,id']])['article_id']);
        $seo = $this->ai->optimizeSeo($article, $request->user()->id);

        $this->discoverOptimization->ensureDiscoverAssets($article, [
            'meta_title' => $seo['meta_title'] ?? $article->title,
            'meta_description' => $seo['meta_description'] ?? $article->excerpt,
            'keywords' => $seo['keywords'] ?? [],
            'og_title' => $seo['og_title'] ?? null,
            'og_description' => $seo['og_description'] ?? null,
            'og_image' => $seo['og_image'] ?? null,
            'discover_optimized' => true,
        ]);

        return response()->json(['seo' => $seo, 'schema' => $this->schema->fullGraph($article)]);
    }

    public function translate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'article_id' => ['required', 'exists:articles,id'],
            'target_locale' => ['required', 'string', 'max:10'],
        ]);

        $article = Article::findOrFail($data['article_id']);
        $translated = $this->ai->translate($article, $data['target_locale'], $request->user()->id);

        return response()->json(['translation' => $translated]);
    }

    public function rewrite(Request $request): JsonResponse
    {
        $data = $request->validate([
            'article_id' => ['required', 'exists:articles,id'],
            'tone' => ['nullable', 'string'],
        ]);

        $article = Article::findOrFail($data['article_id']);
        $rewrite = $this->ai->rewrite($article, $request->user()->id);

        return response()->json(['rewrite' => $rewrite]);
    }

    public function qualityCheck(Request $request): JsonResponse
    {
        $article = Article::findOrFail($request->validate(['article_id' => ['required', 'exists:articles,id']])['article_id']);
        $report = $this->ai->qualityCheck($article, $request->user()->id);

        ContentQualityReport::updateOrCreate(
            ['article_id' => $article->id],
            [
                'plagiarism_score' => $report['plagiarism_risk'] ?? null,
                'hallucination_risk' => $report['hallucination_risk'] ?? null,
                'spam_score' => $report['spam_score'] ?? null,
                'readability_score' => $report['readability_score'] ?? null,
                'seo_score' => $report['seo_score'] ?? null,
                'fact_checks' => $report['fact_checks'] ?? [],
                'recommendations' => $report['recommendations'] ?? [],
            ]
        );

        return response()->json(['report' => $report]);
    }

    public function generateViralContent(Request $request): JsonResponse
    {
        $data = $request->validate([
            'article_id' => ['required', 'exists:articles,id'],
            'platform' => ['required', 'string'],
        ]);

        $article = Article::findOrFail($data['article_id']);
        $content = $this->ai->generateViralContent($article, $data['platform'], $request->user()->id);

        $viral = ViralContent::create([
            'article_id' => $article->id,
            'platform' => $data['platform'],
            'content_type' => 'script',
            'script' => $content['script'] ?? null,
            'assets' => $content,
            'status' => 'draft',
        ]);

        return response()->json(['viral' => $viral, 'content' => $content]);
    }

    public function detectTrends(Request $request): JsonResponse
    {
        $data = $request->validate([
            'signals' => ['nullable', 'array'],
            'sources' => ['nullable', 'array'],
        ]);

        $topics = [];

        if (! empty($data['signals'])) {
            $topics = $this->ai->analyzeTrends($data['signals'], $request->user()->id);
            $this->ai->persistTrends($topics);
        } else {
            $topics = $this->trendDetector->detect($data['sources'] ?? []);
        }

        return response()->json(['topics' => $topics]);
    }

    public function providersStatus(): JsonResponse
    {
        $providers = [
            app(\App\Services\AI\OpenAiProvider::class),
            app(\App\Services\AI\GeminiProvider::class),
            app(\App\Services\AI\GroqProvider::class),
        ];

        $out = [];

        foreach ($providers as $provider) {
            try {
                $response = $provider->chat([
                    ['role' => 'system', 'content' => 'Health check.'],
                    ['role' => 'user', 'content' => 'Reply with OK only.'],
                ], ['max_tokens' => 16]);

                $hasContent = ! empty($response['content']);
                $errorCode = $response['error'] ?? null;
                $errorMessage = $response['message'] ?? null;

                if (! $hasContent && $errorCode) {
                    $errorMessage = $this->humanizeProviderError($provider->name(), $errorCode, $errorMessage);
                }

                $out[$provider->name()] = [
                    'ok' => $hasContent,
                    'error' => $hasContent ? null : ($errorMessage ?? 'Provider returned no content'),
                    'code' => $hasContent ? null : $errorCode,
                    'key_configured' => $this->providerKeyConfigured($provider->name()),
                ];
            } catch (\Throwable $e) {
                $out[$provider->name()] = [
                    'ok' => false,
                    'error' => $this->sanitizeProviderMessage($e->getMessage()),
                    'key_configured' => $this->providerKeyConfigured($provider->name()),
                ];
            }
        }

        $out['elevenlabs'] = [
            'ok' => $this->providerKeyConfigured('elevenlabs'),
            'error' => $this->providerKeyConfigured('elevenlabs') ? null : 'ELEVENLABS_API_KEY not set. Add it in Site Settings before generating reels.',
            'key_configured' => $this->providerKeyConfigured('elevenlabs'),
        ];
  
        return response()->json(['providers' => $out]);
    }

    protected function providerKeyConfigured(string $provider): bool
    {
        $secrets = app(\App\Services\SecretsManager::class);

        return match ($provider) {
            'openai' => ! empty($secrets->get('OPENAI_API_KEY')),
            'gemini' => ! empty($secrets->get('GEMINI_API_KEY')),
            'groq' => ! empty($secrets->get('GROQ_API_KEY')),
            'elevenlabs' => ! empty($secrets->get('ELEVENLABS_API_KEY')),
            default => false,
        };
    }

    protected function humanizeProviderError(string $provider, string $code, ?string $rawMessage): string
    {
        $msg = $this->sanitizeProviderMessage($rawMessage ?? $code);

        if (str_contains($msg, '429') || str_contains(strtolower($msg), 'quota')) {
            return match ($provider) {
                'openai' => 'OpenAI quota exceeded (429). Add billing at platform.openai.com or use a new API key with balance.',
                'gemini' => 'Gemini quota/rate limit hit. Retry later or check Google AI Studio billing.',
                default => 'Rate limit or quota exceeded. Try Groq or wait and retry.',
            };
        }

        if (str_contains($code, 'NOT_SET') || str_contains(strtolower($msg), 'not configured')) {
            return 'API key not set. Add it in Site Settings → Current settings.';
        }

        if (str_contains($msg, '401') || str_contains(strtolower($msg), 'invalid api key')) {
            return 'Invalid API key. Generate a new key and save in Settings.';
        }

        return strlen($msg) > 280 ? substr($msg, 0, 280).'…' : $msg;
    }

    protected function sanitizeProviderMessage(string $message): string
    {
        return preg_replace('/([?&]key=)[^&\s]+/i', '$1[redacted]', $message) ?? $message;
    }
}
