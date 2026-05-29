<?php

namespace App\Services;

use App\Services\AI\NewsAiService;
use App\Services\Trends\GoogleTrendsService;
use App\Services\Trends\TwitterTrendsService;
use App\Services\Trends\YouTubeTrendsService;
use GuzzleHttp\Client;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class TrendDetectorService
{
    public function __construct(
        private readonly NewsAiService $newsAi,
        private readonly GoogleTrendsService $googleTrends,
        private readonly TwitterTrendsService $twitterTrends,
        private readonly YouTubeTrendsService $youtubeTrends
    ) {}

    public function detect(array $sources = []): array
    {
        $signals = array_merge(
            $this->googleTrends->fetchTrending('US'),
            $this->googleTrends->fetchTrending('GB'),
            $this->twitterTrends->fetchByWoeid(23424977), // US
            $this->twitterTrends->fetchByWoeid(23424975), // UK
            $this->youtubeTrends->fetchMostPopular('US', 5),
            $this->youtubeTrends->fetchMostPopular('GB', 5)
        );

        if (empty($sources)) {
            $sources = $this->getDefaultSources();
        }

        foreach ($sources as $source) {
            if (! is_string($source) || trim($source) === '') {
                continue;
            }

            $source = trim($source);
            if (Str::startsWith($source, ['http://', 'https://'])) {
                $signals = array_merge($signals, $this->fetchSourceSignals($source));
            } else {
                $signals[] = ['title' => $source, 'source' => 'signal'];
            }
        }

        $signals = array_values(array_filter($signals));

        if (empty($signals)) {
            $signals = array_values(array_filter($this->getFallbackKeywordSignals()));
            if (! empty($signals)) {
                Log::warning('TrendDetectorService fell back to default keyword signals.');
            }
        }

        if (empty($signals)) {
            Log::warning('TrendDetectorService found no signals for trending analysis.');
            return [];
        }

        try {
            $topics = $this->newsAi->analyzeTrends($signals);
        } catch (\Throwable $e) {
            Log::warning('TrendDetectorService analyzeTrends failed; falling back to raw signal titles.', ['error' => $e->getMessage()]);
            $topics = [];
        }

        if (empty($topics)) {
            Log::warning('TrendDetectorService analyzeTrends returned no topics or failed; falling back to raw signal titles.');
            $topics = $this->buildFallbackTopics($signals);
        }

        if (! empty($topics)) {
            $this->newsAi->persistTrends($topics);
        }

        Log::info('TrendDetectorService detect() returning topics', [
            'topics_type' => gettype($topics),
            'topics_is_array' => is_array($topics),
            'topics_count' => is_array($topics) ? count($topics) : 0,
            'topics_sample' => is_array($topics) && !empty($topics) ? reset($topics) : null,
        ]);

        return $topics;
    }

    protected function getDefaultSources(): array
    {
        $raw = app(\App\Services\SecretsManager::class)->get(
            'TREND_SOURCES',
            'https://feeds.bloomberg.com/markets/news.rss,https://search.cnbc.com/rs/search/combinedcms/view.xml?profile=120000000,https://techcrunch.com/feed/,https://trends.google.com/trends/trendingsearches/daily/rss?geo=US,https://trends.google.com/trends/trendingsearches/daily/rss?geo=GB'
        );
        return array_filter(array_map('trim', explode(',', $raw)));
    }

    protected function getFallbackKeywordSignals(): array
    {
        $signals = [];
        foreach ($this->getDefaultSources() as $source) {
            if (! is_string($source) || trim($source) === '') {
                continue;
            }
            if (Str::startsWith($source, ['http://', 'https://'])) {
                continue;
            }
            $signals[] = ['title' => trim($source), 'source' => 'default_signal'];
        }

        return $signals;
    }

    protected function buildFallbackTopics(array $signals): array
    {
        $topics = [];
        foreach ($signals as $signal) {
            if (empty($signal['title']) || ! is_string($signal['title'])) {
                continue;
            }

            $topics[] = [
                'title' => $this->normalizeFallbackTitle($signal['title']),
                'trend_score' => 50,
                'seo_value' => 50,
                'virality_score' => 50,
                'rpm_potential' => 100,
                'recommended_angle' => 'Use this topic as a starting trend idea.',
                'locale' => 'en',
            ];
        }

        return array_values($topics);
    }

    protected function normalizeFallbackTitle(string $title): string
    {
        $clean = preg_replace('/^#+/', '', trim($title));
        $clean = str_replace(['_', '-'], ' ', $clean);

        if ($clean === '') {
            return $title;
        }

        if (preg_match('/^[A-Za-z0-9 ]+$/', $clean)) {
            return Str::headline($clean) . ' Trends';
        }

        return $clean;
    }

    protected function fetchSourceSignals(string $source): array
    {
        $client = new Client(['timeout' => 12]);
        $signals = [];

        try {
            $response = $client->get($source, [
                'headers' => [
                    'Accept' => 'application/rss+xml, application/xml, text/xml, application/json',
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                ],
            ]);
            $body = (string) $response->getBody();

            if (stripos($response->getHeaderLine('Content-Type'), 'xml') !== false || str_contains($body, '<rss')) {
                $signals = $this->parseRss($body, $source);
            } elseif (str_contains($response->getHeaderLine('Content-Type'), 'json') || str_starts_with(trim($body), '{')) {
                $signals = $this->parseJsonFeed($body, $source);
            }
        } catch (\Throwable $e) {
            Log::warning('TrendDetectorService failed to fetch source', ['source' => $source, 'error' => $e->getMessage()]);
        }

        return $signals;
    }

    protected function parseRss(string $body, string $source): array
    {
        try {
            $xml = new \SimpleXMLElement($body);
            $items = [];
            $namespaces = $xml->getNamespaces(true);
            $htNs = $namespaces['ht'] ?? null;

            foreach ($xml->channel->item as $item) {
                $title = (string) $item->title;

                // Prefer the Google Trends namespaced news item title when available
                if ($htNs) {
                    try {
                        $children = $item->children($htNs);
                        if (isset($children->news_item) && isset($children->news_item->news_item_title)) {
                            $nsTitle = (string) $children->news_item->news_item_title;
                            if (trim($nsTitle) !== '') {
                                $title = $nsTitle;
                            }
                        }
                    } catch (\Throwable $e) {
                        // ignore namespace parsing errors and fallback to <title>
                    }
                }

                $items[] = [
                    'title' => $title,
                    'source' => $source,
                    'url' => (string) $item->link,
                    'published_at' => (string) $item->pubDate,
                ];
            }
            return $items;
        } catch (\Throwable $e) {
            Log::warning('TrendDetectorService failed to parse RSS', ['source' => $source, 'error' => $e->getMessage()]);
            return [];
        }
    }

    protected function parseJsonFeed(string $body, string $source): array
    {
        try {
            $json = json_decode($body, true);
            $items = [];

            if (isset($json['items']) && is_array($json['items'])) {
                foreach ($json['items'] as $item) {
                    $items[] = [
                        'title' => $item['title'] ?? $item['name'] ?? null,
                        'source' => $source,
                        'url' => $item['url'] ?? $item['external_url'] ?? null,
                        'published_at' => $item['date_published'] ?? $item['date_modified'] ?? null,
                    ];
                }
            }

            return array_filter($items, fn($item) => !empty($item['title']));
        } catch (\Throwable $e) {
            Log::warning('TrendDetectorService failed to parse JSON feed', ['source' => $source, 'error' => $e->getMessage()]);
            return [];
        }
    }
}
