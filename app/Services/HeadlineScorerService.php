<?php

namespace App\Services;

use App\Services\AI\AiOrchestrator;

class HeadlineScorerService
{
    public function __construct(private readonly AiOrchestrator $ai)
    {
    }

    /**
     * @param array<int, string> $headlines
     * @return array<int, array<string, mixed>>
     */
    public function score(array $headlines, string $context = '', string $locale = 'en'): array
    {
        $headlines = array_values(array_filter(array_map('trim', $headlines)));
        if (empty($headlines)) {
            return [];
        }

        $system = <<<'PROMPT'
You are an expert Google Discover headline analyst for global English news. Score each headline from 0 to 100 for relevance, freshness, CTR potential, and mobile Discover friendliness. Return valid JSON only as an array of objects with keys: headline, score, reason.
PROMPT;

        $user = "Locale: {$locale}\n";
        $user .= "Context: " . ($context !== '' ? $context : 'No additional context provided.') . "\n";
        $user .= "Headlines:\n";
        foreach ($headlines as $headline) {
            $user .= "- {$headline}\n";
        }

        try {
            $raw = $this->ai->run('headline_score', $system, $user, 'gemini', ['json' => true]);
            $parsed = json_decode($this->extractJson($raw), true);
            if (!is_array($parsed) || empty($parsed)) {
                throw new \RuntimeException('Invalid AI headline score response');
            }

            $variants = $this->normalizeScores($parsed, $headlines);
            if (empty($variants)) {
                throw new \RuntimeException('Normalized result empty');
            }

            return $variants;
        } catch (\Throwable) {
            return array_map(fn (string $headline) => [
                'headline' => $headline,
                'score' => $this->heuristicScore($headline, $context),
                'reason' => 'Heuristic Discover headline score fallback.',
            ], $headlines);
        }
    }

    private function normalizeScores(array $payload, array $headlines): array
    {
        if (isset($payload['scores']) && is_array($payload['scores'])) {
            $payload = $payload['scores'];
        }

        $variants = [];
        foreach ($payload as $item) {
            if (!is_array($item)) {
                continue;
            }

            $headline = trim((string) ($item['headline'] ?? $item['title'] ?? ''));
            if ($headline === '') {
                continue;
            }

            $score = isset($item['score']) ? (int) round(floatval($item['score'])) : $this->heuristicScore($headline);
            $variants[] = [
                'headline' => $headline,
                'score' => min(100, max(0, $score)),
                'reason' => trim((string) ($item['reason'] ?? $item['comment'] ?? 'AI headline quality estimate.')),
            ];
        }

        if (empty($variants)) {
            $variants = array_map(fn (string $headline) => [
                'headline' => $headline,
                'score' => $this->heuristicScore($headline),
                'reason' => 'Heuristic Discover headline score fallback.',
            ], $headlines);
        }

        return $variants;
    }

    private function extractJson(string $raw): string
    {
        if (preg_match('/\{[\s\S]*\}/', $raw, $matches)) {
            return $matches[0];
        }

        if (preg_match('/\[[\s\S]*\]/', $raw, $matches)) {
            return $matches[0];
        }

        return $raw;
    }

    private function heuristicScore(string $headline, string $context = ''): int
    {
        $score = 55;
        $length = mb_strlen($headline);

        if ($length >= 40 && $length <= 85) {
            $score += 15;
        } elseif ($length > 85) {
            $score -= 10;
        } elseif ($length < 30) {
            $score -= 5;
        }

        $powerWords = ['breaking', 'latest', 'today', 'new', 'why', 'how', 'explained', 'warning', 'best', 'major', 'global', 'update'];
        foreach ($powerWords as $word) {
            if (str_contains(mb_strtolower($headline, 'UTF-8'), $word)) {
                $score += 6;
            }
        }

        if (preg_match('/\d+/', $headline)) {
            $score += 5;
        }

        if (str_contains($headline, '?')) {
            $score += 5;
        }

        $keywordBonus = $this->countContextKeywords($headline, $context);
        $score += min(10, $keywordBonus * 2);

        return min(100, max(0, $score));
    }

    private function countContextKeywords(string $headline, string $context): int
    {
        if ($context === '') {
            return 0;
        }

        $headlineWords = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($headline, 'UTF-8')) ?: [];
        $contextWords = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($context, 'UTF-8')) ?: [];
        $contextSet = array_unique(array_filter($contextWords));

        return count(array_filter(array_unique($headlineWords), fn ($word) => $word !== '' && in_array($word, $contextSet, true)));
    }
}
