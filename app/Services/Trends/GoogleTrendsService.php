<?php

namespace App\Services\Trends;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class GoogleTrendsService
{
    public function fetchTrending(string $geo = 'IN'): array
    {
        $signals = array_merge(
            $this->fetchRssFeed("https://trends.google.com/trending/rss?geo={$geo}"),
            $this->fetchRssFeed("https://trends.google.com/trends/trendingsearches/daily/rss?geo={$geo}")
        );

        return array_values(array_filter($signals));
    }

    public function fetchDaily(string $geo = 'IN'): array
    {
        return $this->fetchRssFeed("https://trends.google.com/trends/trendingsearches/daily/rss?geo={$geo}");
    }

    protected function fetchRssFeed(string $url): array
    {
        $client = new Client(['timeout' => 8]);

        try {
            $res = $client->get($url, [
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                    'Accept' => 'application/rss+xml, application/xml, text/xml',
                ],
            ]);

            $body = (string) $res->getBody();
            if (stripos($res->getHeaderLine('Content-Type'), 'xml') !== false || str_contains($body, '<rss')) {
                return $this->parseRss($body, $url);
            }
        } catch (\Throwable $e) {
            Log::warning('GoogleTrendsService failed', ['url' => $url, 'error' => $e->getMessage()]);
        }

        return [];
    }

    protected function parseRss(string $body, string $source): array
    {
        try {
            $xml = new \SimpleXMLElement($body);
            $out = [];
            foreach ($xml->channel->item as $item) {
                $out[] = [
                    'title' => (string) $item->title,
                    'source' => 'google_trends',
                    'url' => (string) $item->link,
                    'published_at' => (string) $item->pubDate,
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            Log::warning('GoogleTrendsService failed to parse RSS', ['source' => $source, 'error' => $e->getMessage()]);
        }

        return [];
    }
}
