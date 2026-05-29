<?php

namespace App\Services\AI;

use App\Models\Article;
use App\Models\TrendingTopic;
use App\Services\AI\ResponseValidator;
use Illuminate\Support\Str;

class NewsAiService
{
    public function __construct(private readonly AiOrchestrator $ai, private readonly ResponseValidator $validator) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function generateDraft(array $context, ?int $userId = null): array
    {
        $system = <<<'PROMPT'
You are an expert global English news editor. Produce helpful, EEAT-compliant journalism for an international audience.
Return valid JSON only with keys: title, excerpt, ai_summary, key_points (array), body (HTML), tags (array), faqs (array of {question, answer}), suggested_category, meta_title, meta_description, keywords (array), reading_time_minutes.
Never generate Hindi, Hinglish, Devanagari, or India-only local-news copy unless a verified global story specifically requires mentioning India. Never fabricate quotes or statistics. Mark uncertain claims. Write in clear English for mobile readers. Set locale to "en" when included.
PROMPT;

        $user = json_encode($context, JSON_UNESCAPED_UNICODE);

        // Prefer Groq first (Settings में अक्सर यही काम करता है); OpenAI/Gemini quota fail होने पर fallback
        $raw = $this->ai->run('generate_draft', $system, $user, 'groq', ['json' => true], $userId);

        $decoded = $this->normalizeDraftPayload(json_decode($this->extractJson($raw), true));

        $check = $this->validator->validateDraft($decoded);
        if (! $check['valid']) {
            $clarify = $system."\n\nReturn ONLY a single JSON object (no markdown fences) with keys: title, excerpt, ai_summary, key_points, body (HTML, min 150 words), tags, faqs.";
            $raw2 = $this->ai->run('generate_draft_retry', $clarify, $user, null, ['json' => true], $userId);
            $decoded2 = $this->normalizeDraftPayload(json_decode($this->extractJson($raw2), true));
            $decoded = array_merge($decoded, $decoded2);
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    public function optimizeSeo(Article $article, ?int $userId = null): array
    {
        $system = 'You are an SEO specialist for Google Discover and global English news. Return JSON: meta_title, meta_description, keywords, schema_faq, internal_link_suggestions (array of slugs), discover_tips. Use English only.';

        $user = json_encode([
            'title' => $article->title,
            'excerpt' => $article->excerpt,
            'locale' => $article->locale,
        ], JSON_UNESCAPED_UNICODE);

        $raw = $this->ai->run('optimize_seo', $system, $user, 'gemini', ['json' => true], $userId, Article::class, $article->id);

        return json_decode($this->extractJson($raw), true) ?: [];
    }

    public function translate(Article $article, string $targetLocale, ?int $userId = null): array
    {
        $system = "Translate the article to locale {$targetLocale}. Preserve facts. Return JSON: title, excerpt, ai_summary, body, key_points.";

        $user = json_encode([
            'title' => $article->title,
            'body' => $article->body,
            'key_points' => $article->key_points,
        ], JSON_UNESCAPED_UNICODE);

        $raw = $this->ai->run('translate', $system, $user, 'gemini', ['json' => true], $userId, Article::class, $article->id);

        return json_decode($this->extractJson($raw), true) ?: [];
    }

    public function rewrite(Article $article, ?int $userId = null): array
    {
        $system = 'Rewrite this article for stronger global English mobile-reader engagement while preserving accuracy. Return JSON: title, excerpt, ai_summary, body, key_points. Use English only.';

        $user = json_encode([
            'title' => $article->title,
            'excerpt' => $article->excerpt,
            'body' => $article->body,
            'locale' => $article->locale,
        ], JSON_UNESCAPED_UNICODE);

        $raw = $this->ai->run('rewrite', $system, $user, 'gemini', ['json' => true], $userId, Article::class, $article->id);

        return json_decode($this->extractJson($raw), true) ?: [];
    }

    /**
     * @return array<string, mixed>
     */
    public function qualityCheck(Article $article, ?int $userId = null): array
    {
        $system = 'Analyze content quality. Return JSON: plagiarism_risk (0-100), hallucination_risk (0-100), spam_score (0-100), readability_score (0-100), seo_score (0-100), recommendations (array), fact_checks (array).';

        $user = Str::limit(strip_tags($article->body), 8000);

        // Claude removed — use OpenAI (or configured default) for quality checks.
        $raw = $this->ai->run('quality_check', $system, $user, 'openai', ['json' => true], $userId, Article::class, $article->id);

        return json_decode($this->extractJson($raw), true) ?: [];
    }

    /**
     * @return array<string, mixed>
     */
    public function generateViralContent(Article $article, string $platform, ?int $userId = null): array
    {
        $system = "Create viral social content for {$platform}. Return JSON: headline, script, hashtags, quote_card_text, caption.";

        $user = json_encode(['title' => $article->title, 'summary' => $article->ai_summary], JSON_UNESCAPED_UNICODE);

        $raw = $this->ai->run('viral_content', $system, $user, 'groq', ['json' => true], $userId, Article::class, $article->id);

        return json_decode($this->extractJson($raw), true) ?: [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $signals
     * @return array<int, array<string, mixed>>
     */
    public function analyzeTrends(array $signals, ?int $userId = null): array
    {
        $system = 'Score trending topics for a global English news site targeting the USA and UK markets. Prioritize high CPM niches (Finance, Tech, Real Estate, Crypto, Business) with massive USA/UK search volume. Return JSON array of objects: title, trend_score, seo_value, virality_score, rpm_potential (must be 200+ for finance/tech), recommended_angle, locale. Use English only and set locale to "en".';

        $raw = $this->ai->run('detect_trends', $system, json_encode($signals), 'groq', ['json' => true], $userId);

        $decoded = json_decode($this->extractJson($raw), true);

        if (isset($decoded['topics'])) {
            return $decoded['topics'];
        }

        return is_array($decoded) ? $decoded : [];
    }

    public function persistTrends(array $topics): void
    {
        foreach ($topics as $topic) {
            TrendingTopic::updateOrCreate(
                ['title' => $topic['title'], 'source' => 'ai_analysis'],
                [
                    'locale' => $topic['locale'] ?? 'en',
                    'trend_score' => $topic['trend_score'] ?? 0,
                    'seo_value' => $topic['seo_value'] ?? null,
                    'virality_score' => $topic['virality_score'] ?? null,
                    'rpm_potential' => $topic['rpm_potential'] ?? null,
                    'metadata' => $topic,
                    'status' => 'detected',
                    'detected_at' => now(),
                ]
            );
        }
    }

    private function extractJson(string $raw): string
    {
        $raw = trim($raw);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $raw, $fence)) {
            $raw = trim($fence[1]);
        }

        if (preg_match('/\{[\s\S]*\}/', $raw, $matches)) {
            return $matches[0];
        }
        if (preg_match('/\[[\s\S]*\]/', $raw, $matches)) {
            return $matches[0];
        }

        return $raw;
    }

    /**
     * @param  mixed  $decoded
     * @return array<string, mixed>
     */
    private function normalizeDraftPayload(mixed $decoded): array
    {
        if (! is_array($decoded)) {
            return [];
        }

        if (array_is_list($decoded) && isset($decoded[0]) && is_array($decoded[0])) {
            return $decoded[0];
        }

        return $decoded;
    }
}
