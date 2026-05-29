<?php

namespace App\Services;

use App\Models\Article;
use App\Models\HeadlineExperiment;
use App\Models\HeadlineVariant;
use Illuminate\Support\Arr;

class HeadlineExperimentService
{
    public function __construct(private readonly HeadlineScorerService $scorer) {}

    public function createExperiment(Article $article, array $headlines, array $options = []): HeadlineExperiment
    {
        $locale = $options['locale'] ?? $article->locale ?? 'en';
        $scoreContext = trim($options['context'] ?? implode("\n", array_filter([$article->title, $article->excerpt, strip_tags($article->body ?? '')])));

        $experiment = HeadlineExperiment::create([
            'article_id' => $article->id,
            'title' => $options['title'] ?? 'Headline A/B Test',
            'description' => $options['description'] ?? 'A/B testing candidate headlines for Discover and CTR.',
            'locale' => $locale,
            'status' => 'running',
            'created_by' => $options['created_by'] ?? null,
            'metadata' => Arr::except($options, ['locale', 'title', 'description', 'created_by', 'context']),
        ]);

        $scores = $this->scorer->score($headlines, $scoreContext, $locale);

        foreach ($scores as $variantData) {
            HeadlineVariant::create([
                'headline_experiment_id' => $experiment->id,
                'headline' => $variantData['headline'] ?? ($variantData['text'] ?? ''),
                'score' => min(100, max(0, (int) ($variantData['score'] ?? 0))),
                'reason' => $variantData['reason'] ?? $variantData['comment'] ?? 'AI headline quality estimate',
            ]);
        }

        return $experiment->load('variants');
    }

    public function recordVariantEvent(HeadlineVariant $variant, string $eventType): HeadlineVariant
    {
        if (! in_array($eventType, ['impression', 'click'], true)) {
            throw new \InvalidArgumentException("Unsupported event type: {$eventType}");
        }

        if ($eventType === 'impression') {
            $variant->increment('impressions_count');
        }

        if ($eventType === 'click') {
            $variant->increment('clicks_count');
        }

        $variant->refresh();
        $variant->ctr = $variant->impressions_count > 0
            ? round(($variant->clicks_count / $variant->impressions_count) * 100, 2)
            : 0;
        $variant->save();

        return $variant;
    }

    public function finalizeExperiment(HeadlineExperiment $experiment, ?HeadlineVariant $winner = null): HeadlineExperiment
    {
        if (! $winner) {
            $winner = $experiment->variants->sortByDesc(fn (HeadlineVariant $variant) => [$variant->ctr, $variant->score])->first();
        }

        if ($winner) {
            $experiment->winner_variant_id = $winner->id;
            $experiment->status = 'completed';
            $experiment->save();
        }

        return $experiment->load(['variants', 'winner']);
    }
}
