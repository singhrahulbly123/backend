<?php

namespace App\Services\Trends;

use App\Services\SecretsManager;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class TwitterTrendsService
{
    public function __construct(private readonly SecretsManager $secrets) {}

    public function fetchByWoeid(int $woeid = 23424848): array
    {
        // Default WOEID 23424848 = India (approx). Use TWITTER_BEARER_TOKEN if present.
        $token = $this->secrets->get('TWITTER_BEARER_TOKEN');
        if (!$token) return [];

        $client = new Client(['base_uri' => 'https://api.twitter.com/']);
        try {
            // Twitter API v1.1 endpoint for trends/place requires OAuth 1.0a or app token; some accounts may not have access.
            $res = $client->get('1.1/trends/place.json', [
                'query' => ['id' => $woeid],
                'headers' => ['Authorization' => "Bearer {$token}"],
                'timeout' => 8,
            ]);

            $data = json_decode((string) $res->getBody(), true);
            $out = [];
            foreach ($data[0]['trends'] ?? [] as $t) {
                $out[] = [
                    'title' => $t['name'] ?? '',
                    'source' => 'twitter_trends',
                    'query' => $t['query'] ?? null,
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            Log::warning('TwitterTrendsService failed', ['error' => $e->getMessage()]);
        }
        return [];
    }
}
