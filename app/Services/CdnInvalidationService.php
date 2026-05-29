<?php

namespace App\Services;

use App\Models\Article;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CdnInvalidationService
{
    public function __construct(private readonly SecretsManager $secrets) {}

    public function purgeArticle(Article $article): bool
    {
        $baseUrl = rtrim(config('app.url'), '/');
        $urls = [
            $baseUrl.'/articles/'.$article->slug,
            $baseUrl.'/',
            $baseUrl.'/articles',
        ];

        if ($article->category?->slug) {
            $urls[] = $baseUrl.'/category/'.$article->category->slug;
            $urls[] = $baseUrl.'/categories/'.$article->category->slug;
        }

        return $this->purgeUrls(array_unique($urls));
    }

    public function purgeUrls(array $urls): bool
    {
        if (empty($urls)) {
            return false;
        }

        switch (config('cdn.provider', 'cloudflare')) {
            case 'cloudflare':
                return $this->purgeCloudflare($urls);
            default:
                Log::debug('CDN invalidation skipped, unknown provider configured.', [
                    'provider' => config('cdn.provider'),
                    'urls' => $urls,
                ]);

                return false;
        }
    }

    protected function purgeCloudflare(array $urls): bool
    {
        $zoneId = config('cdn.cloudflare.zone_id');
        $apiToken = $this->secrets->get('CLOUDFLARE_API_TOKEN');

        if (! $zoneId || ! $apiToken) {
            Log::warning('Cloudflare CDN purge skipped due to missing zone ID or API token.');
            return false;
        }

        $endpoint = "https://api.cloudflare.com/client/v4/zones/{$zoneId}/purge_cache";

        $response = Http::withToken($apiToken)
            ->acceptJson()
            ->post($endpoint, ['files' => array_values($urls)]);

        if ($response->failed()) {
            Log::error('Cloudflare purge request failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        }

        $payload = $response->json();
        if (! ($payload['success'] ?? false)) {
            Log::error('Cloudflare purge returned an unsuccessful response.', ['response' => $payload]);
            return false;
        }

        Log::info('Cloudflare cache purge requested.', ['urls' => $urls]);

        return true;
    }
}
