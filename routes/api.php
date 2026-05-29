<?php

use App\Http\Controllers\Api\V1\AdController;
use App\Http\Controllers\Api\V1\AffiliateController;
use App\Http\Controllers\Api\V1\AiComparisonController;
use App\Http\Controllers\Api\V1\AiSkillGuideController;
use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\ArticleController;
use App\Http\Controllers\Api\V1\AuthorController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BreakingNewsController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CommentController;
use App\Http\Controllers\Api\V1\AiWorkflowController;
use App\Http\Controllers\Api\V1\GrowthContentController;
use App\Http\Controllers\Api\V1\LearningController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\MiniToolController;
use App\Http\Controllers\Api\V1\NewsletterController;
use App\Http\Controllers\Api\V1\PushSubscriptionController;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\SeoController;
use App\Http\Controllers\Api\V1\TrendingController;
use App\Http\Controllers\Api\V1\WebStoryController;
use App\Http\Controllers\Api\V1\Admin\DashboardController;
use App\Http\Controllers\Api\V1\Admin\AiComparisonAdminController;
use App\Http\Controllers\Api\V1\Admin\AiSkillGuideAdminController;
use App\Http\Controllers\Api\V1\Admin\ArticleAdminController;
use App\Http\Controllers\Api\V1\Admin\HeadlineExperimentController;
use App\Http\Controllers\Api\V1\Admin\LearningAdminController;
use App\Http\Controllers\Api\V1\Admin\GrowthContentAdminController;
use App\Http\Controllers\Api\V1\Admin\UserAdminController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/health', fn () => response()->json(['status' => 'ok', 'service' => 'aihindinews-api']));

    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
    Route::get('/auth/roles', [AuthController::class, 'roles']);

    Route::get('/articles', [ArticleController::class, 'index']);
    Route::get('/articles/featured', [ArticleController::class, 'featured']);
    Route::get('/articles/viral', [ArticleController::class, 'viral']);
    Route::get('/articles/{slug}', [ArticleController::class, 'show']);
    Route::post('/articles/{slug}/view', [ArticleController::class, 'recordView'])->middleware('throttle:60,1');
    Route::get('/authors/{slug}', [AuthorController::class, 'show']);

    Route::get('/push/topics', [PushSubscriptionController::class, 'topics']);
    Route::post('/push/subscribe', [PushSubscriptionController::class, 'subscribe']);
    Route::post('/push/preferences', [PushSubscriptionController::class, 'updatePreferences'])->middleware('throttle:30,1');

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{slug}', [CategoryController::class, 'show']);

    Route::get('/breaking', [BreakingNewsController::class, 'index']);
    Route::get('/trending', [TrendingController::class, 'index']);
    Route::get('/trending/topics', [TrendingController::class, 'topics']);

    Route::get('/web-stories', [WebStoryController::class, 'index']);
    Route::get('/web-stories/{slug}', [WebStoryController::class, 'show']);
    Route::post('/web-stories/generate', [\App\Http\Controllers\Api\V1\WebStoryPipelineController::class, 'generate'])->middleware('throttle:20,1');
    Route::post('/web-stories/{slug}/tts', [\App\Http\Controllers\Api\V1\WebStoryPipelineController::class, 'tts'])->middleware('throttle:10,1');
    Route::post('/web-stories/{slug}/video', [\App\Http\Controllers\Api\V1\WebStoryPipelineController::class, 'video'])->middleware('throttle:5,1');

    Route::get('/ai-tools', [GrowthContentController::class, 'tools']);
    Route::post('/ai-tools/finder', [GrowthContentController::class, 'toolFinder'])->middleware('throttle:60,1');
    Route::get('/ai-tools/web-stories', [GrowthContentController::class, 'toolStories']);
    Route::get('/ai-tools/{slug}/web-story', [GrowthContentController::class, 'toolStory']);
    Route::get('/ai-tools/{slug}', [GrowthContentController::class, 'tool']);
    Route::get('/prompts', [GrowthContentController::class, 'prompts']);
    Route::get('/prompts/{slug}', [GrowthContentController::class, 'prompt']);
    Route::post('/prompts/{promptTemplate}/copy', [GrowthContentController::class, 'recordPromptCopy'])->middleware('throttle:60,1');
    Route::get('/daily-briefs', [GrowthContentController::class, 'briefs']);
    Route::get('/daily-briefs/latest', [GrowthContentController::class, 'latestBrief']);
    Route::get('/daily-briefs/latest/voice', [GrowthContentController::class, 'latestVoiceBrief']);
    Route::get('/daily-briefs/{slug}', [GrowthContentController::class, 'brief']);
    Route::get('/personalized-feed', [GrowthContentController::class, 'personalizedFeed']);
    Route::post('/mini-tools/{tool}', [MiniToolController::class, 'generate'])->middleware('throttle:30,1');
    Route::get('/comparisons', [AiComparisonController::class, 'index']);
    Route::get('/comparisons/{slug}', [AiComparisonController::class, 'show']);
    Route::get('/learning-paths', [LearningController::class, 'paths']);
    Route::get('/learning-paths/{slug}', [LearningController::class, 'path']);
    Route::get('/learning-paths/{pathSlug}/lessons/{lessonSlug}', [LearningController::class, 'lesson']);
    Route::get('/ai-skills', [AiSkillGuideController::class, 'index']);
    Route::get('/ai-skills/{slug}', [AiSkillGuideController::class, 'show']);
    Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])->middleware('throttle:10,1');

    Route::get('/search', [SearchController::class, 'search']);
    Route::get('/search/suggest', [SearchController::class, 'suggest']);

    Route::get('/seo/sitemap', [SeoController::class, 'sitemap']);
    Route::get('/seo/schema/{slug}', [SeoController::class, 'articleSchema']);

    Route::get('/ads/placements', [AdController::class, 'placements']);
    Route::get('/affiliate/{slug}', [AffiliateController::class, 'show']);
    Route::post('/affiliate/{slug}/click', [AffiliateController::class, 'trackClick'])->middleware('throttle:30,1');
    Route::post('/affiliate/{slug}/conversion', [AffiliateController::class, 'trackConversion'])->middleware('throttle:60,1');

    Route::get('/articles/{slug}/comments', [CommentController::class, 'index']);
    Route::post('/articles/{slug}/comments', [CommentController::class, 'store'])->middleware('throttle:10,1');

    Route::post('/analytics/event', [AnalyticsController::class, 'track'])->middleware('throttle:120,1');

    // Public AI chat endpoint (rate-limited)
    Route::post('/ai/chat', [\App\Http\Controllers\Api\V1\AiController::class, 'chat'])->middleware('throttle:30,1');

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);

        Route::middleware(['role:super_admin,editor,journalist,seo_manager,ai_reviewer'])->prefix('admin')->group(function () {
            Route::get('/dashboard', [DashboardController::class, 'index']);

            Route::get('/articles/reels/preflight', [ArticleAdminController::class, 'reelPreflight']);
            Route::apiResource('articles', ArticleAdminController::class);
            Route::post('/articles/{article}/publish', [ArticleAdminController::class, 'publish']);
            Route::post('/articles/{article}/approve', [ArticleAdminController::class, 'approve']);
            Route::post('/articles/{article}/reject', [ArticleAdminController::class, 'reject']);
            Route::post('/articles/{article}/assign', [ArticleAdminController::class, 'assign']);
            Route::post('/articles/{article}/schedule', [ArticleAdminController::class, 'schedule']);
            Route::post('/articles/{article}/autosave', [ArticleAdminController::class, 'autosave']);
            Route::post('/articles/{article}/reels', [ArticleAdminController::class, 'generateReel']);
            Route::get('/articles/{article}/reels/{job}', [ArticleAdminController::class, 'reelStatus']);
            Route::get('/articles/{article}/revisions', [ArticleAdminController::class, 'revisions']);
            Route::post('/articles/{article}/revisions/{revision}/restore', [ArticleAdminController::class, 'restoreRevision']);

            Route::apiResource('users', UserAdminController::class)->middleware('role:super_admin,editor');

            Route::post('/ai/generate-draft', [AiWorkflowController::class, 'generateDraft']);
            Route::post('/ai/optimize-seo', [AiWorkflowController::class, 'optimizeSeo']);
            Route::post('/ai/translate', [AiWorkflowController::class, 'translate']);
            Route::post('/ai/rewrite', [AiWorkflowController::class, 'rewrite']);
            Route::post('/ai/quality-check', [AiWorkflowController::class, 'qualityCheck']);
            Route::post('/ai/viral-content', [AiWorkflowController::class, 'generateViralContent']);
            // Generate or refresh AI summary for an article
            Route::post('/ai/articles/{article}/summary', [\App\Http\Controllers\Api\V1\AiController::class, 'generateArticleSummary'])->middleware('throttle:10,1');
            Route::post('/ai/articles/{article}/headline-score', [\App\Http\Controllers\Api\V1\AiController::class, 'scoreHeadlines'])->middleware('throttle:10,1');
            Route::post('/ai/detect-trends', [AiWorkflowController::class, 'detectTrends']);
            Route::get('/ai/providers/status', [\App\Http\Controllers\Api\V1\AiWorkflowController::class, 'providersStatus']);
            Route::get('/settings', [\App\Http\Controllers\Api\V1\Admin\SettingAdminController::class, 'index']);
            Route::post('/settings', [\App\Http\Controllers\Api\V1\Admin\SettingAdminController::class, 'store']);

            Route::get('/growth/ai-tools', [GrowthContentAdminController::class, 'tools']);
            Route::post('/growth/ai-tools', [GrowthContentAdminController::class, 'storeTool']);
            Route::post('/growth/ai-tools/{tool}/reels', [GrowthContentAdminController::class, 'generateToolReel']);
            Route::put('/growth/ai-tools/{tool}', [GrowthContentAdminController::class, 'updateTool']);
            Route::delete('/growth/ai-tools/{tool}', [GrowthContentAdminController::class, 'destroyTool']);
            Route::get('/growth/prompts', [GrowthContentAdminController::class, 'prompts']);
            Route::post('/growth/prompts', [GrowthContentAdminController::class, 'storePrompt']);
            Route::put('/growth/prompts/{promptTemplate}', [GrowthContentAdminController::class, 'updatePrompt']);
            Route::delete('/growth/prompts/{promptTemplate}', [GrowthContentAdminController::class, 'destroyPrompt']);
            Route::get('/growth/daily-briefs', [GrowthContentAdminController::class, 'briefs']);
            Route::post('/growth/daily-briefs', [GrowthContentAdminController::class, 'storeBrief']);
            Route::post('/growth/daily-briefs/{dailyAiBrief}/reels', [GrowthContentAdminController::class, 'generateBriefReel']);
            Route::put('/growth/daily-briefs/{dailyAiBrief}', [GrowthContentAdminController::class, 'updateBrief']);
            Route::delete('/growth/daily-briefs/{dailyAiBrief}', [GrowthContentAdminController::class, 'destroyBrief']);
            Route::get('/growth/reels/{job}', [GrowthContentAdminController::class, 'reelStatus']);
            Route::apiResource('/growth/comparisons', AiComparisonAdminController::class)->parameters(['comparisons' => 'comparison']);
            Route::get('/learning/paths', [LearningAdminController::class, 'paths']);
            Route::post('/learning/paths', [LearningAdminController::class, 'storePath']);
            Route::put('/learning/paths/{learningPath}', [LearningAdminController::class, 'updatePath']);
            Route::get('/learning/lessons', [LearningAdminController::class, 'lessons']);
            Route::post('/learning/lessons', [LearningAdminController::class, 'storeLesson']);
            Route::put('/learning/lessons/{learningLesson}', [LearningAdminController::class, 'updateLesson']);
            Route::apiResource('/learning/skill-guides', AiSkillGuideAdminController::class)->only(['index', 'store', 'update'])->parameters(['skill-guides' => 'aiSkillGuide']);

            Route::get('/articles/{article}/headline-experiments', [HeadlineExperimentController::class, 'index']);
            Route::post('/articles/{article}/headline-experiments', [HeadlineExperimentController::class, 'store']);
            Route::get('/headline-experiments/{headlineExperiment}', [HeadlineExperimentController::class, 'show']);
            Route::post('/headline-experiments/{headlineExperiment}/finalize', [HeadlineExperimentController::class, 'finalize']);
            Route::post('/headline-experiments/{headlineExperiment}/variants/{variant}/record', [HeadlineExperimentController::class, 'recordVariantEvent']);
            Route::get('/analytics/discover-summary', [AnalyticsController::class, 'discoverSummary']);
            Route::get('/analytics/affiliate-summary', [AnalyticsController::class, 'affiliateSummary']);
            Route::post('/push/send', [PushSubscriptionController::class, 'send']);
            Route::post('/telegram/daily-brief', fn (\App\Services\TelegramDigestService $service, \Illuminate\Http\Request $request) => response()->json($service->publishDailyBrief(null, $request->boolean('dry_run'))));

            Route::post('/media/upload', [MediaController::class, 'upload']);
            Route::delete('/media/{media}', [MediaController::class, 'destroy']);
        });
    });
});
