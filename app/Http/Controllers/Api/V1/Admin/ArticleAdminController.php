<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ArticleResource;
use App\Jobs\GenerateGrowthReelJob;
use App\Services\ArticleVideoService;
use App\Models\Article;
use App\Models\ArticleRevision;
use App\Models\AuditLog;
use App\Models\LiveUpdate;
use App\Services\DiscoverOptimizationService;
use App\Services\Search\MeilisearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ArticleAdminController extends Controller
{
    public function __construct(
        private readonly MeilisearchService $search,
        private readonly DiscoverOptimizationService $discoverOptimization
    ) {}

    public function index(Request $request): JsonResponse
    {
        $articles = Article::with(['category', 'author', 'seoMeta'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(25);

        return ArticleResource::collection($articles)->response();
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['author_id'] = $request->user()->id;
        $data['slug'] = $data['slug'] ?? Str::slug($data['title']).'-'.Str::random(5);

        if (($data['status'] ?? null) === 'published') {
            $data['published_at'] = $data['published_at'] ?? now();
            $data['human_reviewed'] = $data['human_reviewed'] ?? true;
        }

        $article = Article::create($data);
        $this->syncRelations($article, $request);

        if ($article->status === 'published') {
            $this->discoverOptimization->ensureDiscoverAssets($article);
            $this->recordLiveUpdate($article, $request->user()->id);
            $this->search->indexArticle($article);
            Cache::forget("articles:show:{$article->slug}");
        }

        $this->audit($request, 'article.created', $article);

        return (new ArticleResource($article->load(['category', 'tags', 'seoMeta'])))->response()->setStatusCode(201);
    }

    public function show(Article $article): JsonResponse
    {
        return (new ArticleResource($article->load(['category', 'tags', 'seoMeta', 'revisions', 'qualityReport'])))->response();
    }

    public function update(Request $request, Article $article): JsonResponse
    {
        ArticleRevision::create([
            'article_id' => $article->id,
            'user_id' => $request->user()->id,
            'body' => $article->body,
            'metadata' => ['title' => $article->title],
            'revision_note' => $request->get('revision_note'),
        ]);

        $article->update($this->validated($request, $article));
        $this->syncRelations($article, $request);
        $this->audit($request, 'article.updated', $article);

        if ($article->status === 'published') {
            $this->discoverOptimization->ensureDiscoverAssets($article);
            $this->recordLiveUpdate($article, $request->user()->id);
            $this->search->indexArticle($article);
            Cache::forget("articles:show:{$article->slug}");
        }

        return (new ArticleResource($article->fresh()->load(['category', 'tags', 'seoMeta'])))->response();
    }

    public function destroy(Article $article): JsonResponse
    {
        $article->delete();

        return response()->json(['message' => 'Archived.']);
    }

    public function publish(Article $article): JsonResponse
    {
        $article->update([
            'status' => 'published',
            'published_at' => now(),
            'human_reviewed' => true,
        ]);

        $this->discoverOptimization->ensureDiscoverAssets($article);
        $this->recordLiveUpdate($article, request()->user()->id);
        $this->search->indexArticle($article);
        Cache::forget("articles:show:{$article->slug}");

        return response()->json(['data' => new ArticleResource($article->fresh()->load(['seoMeta']))]);
    }

    public function generateReel(Request $request, Article $article): JsonResponse
    {
        $data = $request->validate([
            'voice' => ['nullable', 'string', 'max:128'],
            'platform' => ['nullable', 'in:instagram_reel,youtube_short,facebook_reel,web_story'],
            'include_subtitles' => ['boolean'],
        ]);

        $jobId = 'reel_' . Str::random(12);
        $voice = $data['voice'] ?? 'alloy';
        $platform = $data['platform'] ?? 'instagram_reel';
        $includeSubtitles = $data['include_subtitles'] ?? true;

        Storage::disk('public')->put("videos/{$jobId}.status.json", json_encode([
            'job_id' => $jobId,
            'status' => 'queued',
            'article_id' => $article->id,
            'platform' => $platform,
            'updated_at' => now()->toDateTimeString(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        GenerateGrowthReelJob::dispatch($jobId, 'article', $article->id, $platform, $voice, $includeSubtitles, true);

        return response()->json(['data' => [
            'job_id' => $jobId,
            'article_id' => $article->id,
            'source_type' => 'article',
            'source_id' => $article->id,
            'status' => 'queued',
            'platform' => $platform,
        ]]);
    }

    public function reelPreflight(ArticleVideoService $videoService): JsonResponse
    {
        return response()->json(['data' => $videoService->preflight()]);
    }

    public function reelStatus(Article $article, string $job): JsonResponse
    {
        $statusPath = storage_path("app/public/videos/{$job}.status.json");
        if (! file_exists($statusPath)) {
            return response()->json(['message' => 'Reel status not found.'], 404);
        }

        $payload = json_decode(file_get_contents($statusPath), true);
        $belongsToArticle = ($payload['article_id'] ?? null) === $article->id
            || (($payload['source_type'] ?? null) === 'article' && ($payload['source_id'] ?? null) === $article->id);

        if (! is_array($payload) || ! $belongsToArticle) {
            return response()->json(['message' => 'Reel job does not belong to this article.'], 404);
        }

        return response()->json(['data' => $payload]);
    }

    public function approve(Article $article): JsonResponse
    {
        $article->update([
            'status' => 'published',
            'published_at' => now(),
            'human_reviewed' => true,
            'editor_id' => request()->user()->id,
        ]);

        $this->discoverOptimization->ensureDiscoverAssets($article);
        $this->recordLiveUpdate($article, request()->user()->id);
        $this->search->indexArticle($article);
        Cache::forget("articles:show:{$article->slug}");

        $this->audit(request(), 'article.approved', $article);

        return response()->json(['data' => new ArticleResource($article->fresh()->load(['seoMeta']))]);
    }

    public function reject(Request $request, Article $article): JsonResponse
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:1000']]);

        $article->update([
            'status' => 'draft',
        ]);

        $this->audit($request, 'article.rejected', $article);

        return response()->json(['message' => 'Article rejected and moved to drafts.']);
    }

    public function assign(Request $request, Article $article): JsonResponse
    {
        $data = $request->validate(['user_id' => ['nullable', 'exists:users,id']]);

        $assignee = $data['user_id'] ?? $request->user()->id;
        $article->update(['editor_id' => $assignee]);

        $this->audit($request, 'article.assigned', $article);

        return response()->json(['data' => new ArticleResource($article)]);
    }

    public function schedule(Request $request, Article $article): JsonResponse
    {
        $request->validate(['scheduled_at' => ['required', 'date', 'after:now']]);

        $article->update([
            'status' => 'scheduled',
            'scheduled_at' => $request->scheduled_at,
        ]);

        return response()->json(['data' => $article]);
    }

    public function autosave(Request $request, Article $article): JsonResponse
    {
        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string'],
            'excerpt' => ['nullable', 'string'],
            'seo' => ['nullable', 'array'],
        ]);

        // Update only provided fields without creating a full revision
        $update = [];
        foreach (['title', 'body', 'excerpt'] as $f) {
            if (array_key_exists($f, $data)) $update[$f] = $data[$f];
        }

        if (!empty($update)) {
            $article->update($update);
        }

        if (isset($data['seo']) && is_array($data['seo'])) {
            $article->seoMeta()->updateOrCreate([], $data['seo']);
        }

        // Record lightweight audit
        $this->audit($request, 'article.autosave', $article);

        return response()->json(['status' => 'ok', 'saved_at' => now()->toDateTimeString()]);
    }

    public function revisions(Article $article): JsonResponse
    {
        return response()->json(['data' => $article->revisions()->with('user:id,name')->latest()->get()]);
    }

    public function restoreRevision(Article $article, ArticleRevision $revision): JsonResponse
    {
        if ($revision->article_id !== $article->id) {
            return response()->json(['message' => 'Revision does not belong to this article.'], 404);
        }

        ArticleRevision::create([
            'article_id' => $article->id,
            'user_id' => request()->user()->id,
            'body' => $article->body,
            'metadata' => ['title' => $article->title],
            'revision_note' => 'Snapshot before restore',
        ]);

        $article->update([
            'title' => $revision->metadata['title'] ?? $article->title,
            'body' => $revision->body,
            'editor_id' => request()->user()->id,
            'status' => 'review',
        ]);

        $this->audit(request(), 'article.revision_restored', $article);

        return response()->json(['data' => new ArticleResource($article->fresh()->load(['category', 'tags', 'seoMeta', 'revisions', 'qualityReport']))]);
    }

    private function validated(Request $request, ?Article $article = null): array
    {
        return $request->validate([
            'title' => [$article ? 'sometimes' : 'required', 'string', 'max:500'],
            'slug' => ['nullable', 'string', 'unique:articles,slug,'.($article?->id ?? 'NULL')],
            'locale' => ['nullable', 'string', 'max:10'],
            'content_type' => ['nullable', 'string'],
            'excerpt' => ['nullable', 'string'],
            'ai_summary' => ['nullable', 'string'],
            'key_points' => ['nullable', 'array'],
            'body' => [$article ? 'sometimes' : 'required', 'string'],
            'featured_image' => ['nullable', 'string'],
            'category_id' => ['nullable', 'exists:categories,id'],
            'status' => ['nullable', 'in:draft,review,scheduled,published,archived'],
            'is_breaking' => ['boolean'],
            'is_featured' => ['boolean'],
            'faqs' => ['nullable', 'array'],
            'sources' => ['nullable', 'array'],
            'timeline' => ['nullable', 'array'],
        ]);
    }

    private function syncRelations(Article $article, Request $request): void
    {
        if ($request->has('tag_ids')) {
            $article->tags()->sync($request->tag_ids);
        }

        if ($request->has('seo')) {
            $article->seoMeta()->updateOrCreate([], $request->seo);
        }
    }

    private function recordLiveUpdate(Article $article, int $userId): void
    {
        if ($article->status !== 'published' || ! $article->wasChanged(['title', 'excerpt', 'body', 'status'])) {
            return;
        }

        $content = $article->excerpt ?: trim(strip_tags(substr($article->body ?? '', 0, 280)));
        if ($content === '') {
            $content = 'Article updated for Google Discover and live readers.';
        }

        LiveUpdate::create([
            'article_id' => $article->id,
            'user_id' => $userId,
            'headline' => $article->title,
            'content' => $content,
            'is_breaking' => $article->is_breaking,
            'published_at' => now(),
        ]);
    }

    private function audit(Request $request, string $action, Article $article): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'auditable_type' => Article::class,
            'auditable_id' => $article->id,
            'ip_address' => $request->ip(),
        ]);
    }
}
