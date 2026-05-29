<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\TrendDetectorService;
use App\Services\AI\NewsAiService;
use App\Services\SEO\SeoEngineService;
use App\Services\Search\MeilisearchService;
use App\Models\Article;

class RunAiPipeline extends Command
{
    protected $signature = 'ai:run-pipeline {--limit=10}';
    protected $description = 'Run AI content pipeline: detect trends, analyze and create drafts.';

    public function handle(TrendDetectorService $trendDetector, NewsAiService $newsAi, SeoEngineService $seoEngine, MeilisearchService $search)
    {
        $this->info('Fetching trend signals...');
        $topics = $trendDetector->detect();

        if (empty($topics)) {
            $this->info('No topics found.');
            return 0;
        }

        $limit = (int) $this->option('limit');
        $count = 0;

        foreach ($topics as $topic) {
            if ($count >= $limit) break;

            $this->info('Analyzing: ' . ($topic['title'] ?? 'untitled'));
            $draft = $newsAi->generateDraft(['topic' => $topic['title'], 'locale' => $topic['locale'] ?? 'en'], null);

            if (!empty($draft['title'])) {
                $article = Article::create([
                    'title' => $draft['title'] ?? ($topic['title'] ?? 'AI Draft'),
                    'excerpt' => $draft['excerpt'] ?? null,
                    'ai_summary' => $draft['ai_summary'] ?? null,
                    'body' => $draft['body'] ?? null,
                    'locale' => $draft['locale'] ?? ($topic['locale'] ?? 'en'),
                    'status' => 'review',
                    'is_ai_generated' => true,
                    'author_id' => 1,
                ]);

                $this->info('Created draft: ' . $article->id);

                // Optimize SEO and decide action
                $decision = $seoEngine->optimizeAndDecidePublish($article, $newsAi, $topic);

                if ($decision['action'] === 'publish') {
                    $article->update(['status' => 'published', 'published_at' => now(), 'human_reviewed' => false]);
                    $search->indexArticle($article);
                    $this->info('Auto-published: ' . $article->id);
                } elseif ($decision['action'] === 'schedule') {
                    $delay = $decision['delay_minutes'] ?? 60;
                    $article->update(['status' => 'scheduled', 'scheduled_at' => now()->addMinutes($delay)]);
                    $this->info('Scheduled article: ' . $article->id . ' in ' . $delay . ' minutes');
                } else {
                    $this->info('Left as review: ' . $article->id);
                }

                $count++;
            }
        }

        $this->info("Pipeline complete. Drafts created: {$count}");
        return 0;
    }
}
