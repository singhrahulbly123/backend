<?php

namespace App\Services\Trends;

use App\Services\SecretsManager;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class YouTubeTrendsService
{
    public function __construct(private readonly SecretsManager $secrets) {}

    public function fetchMostPopular(string $region = 'IN', int $limit = 10): array
    {
        $key = $this->secrets->get('YOUTUBE_API_KEY');
        if (!$key) return [];

        $client = new Client(['base_uri' => 'https://www.googleapis.com/']);
        try {
            $res = $client->get('youtube/v3/videos', [
                'query' => [
                    'part' => 'snippet,statistics',
                    'chart' => 'mostPopular',
                    'regionCode' => $region,
                    'maxResults' => $limit,
                    'key' => $key,
                ],
                'timeout' => 8,
            ]);

            $data = json_decode((string) $res->getBody(), true);
            $out = [];
            foreach ($data['items'] ?? [] as $video) {
                $out[] = [
                    'title' => $video['snippet']['title'] ?? '',
                    'source' => 'youtube_trends',
                    'url' => 'https://www.youtube.com/watch?v=' . ($video['id'] ?? ''),
                    'views' => $video['statistics']['viewCount'] ?? null,
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            Log::warning('YouTubeTrendsService failed', ['error' => $e->getMessage()]);
        }
        return [];
    }
}
