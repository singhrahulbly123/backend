<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class MeiliClient
{
    protected $http;
    protected $host;
    protected $key;

    public function __construct(SecretsManager $secrets)
    {
        $this->host = env('MEILISEARCH_HOST', 'http://127.0.0.1:7700');
        $this->key = $secrets->get('MEILISEARCH_KEY', env('MEILISEARCH_KEY'));
        $this->http = new Client(['base_uri' => rtrim($this->host, '/') . '/']);
    }

    protected function headers(): array
    {
        $h = ['Content-Type' => 'application/json'];
        if (!empty($this->key)) {
            $h['X-Meili-API-Key'] = $this->key;
        }
        return $h;
    }

    public function createIndex(string $index, array $options = []): array
    {
        try {
            $res = $this->http->post('indexes', [
                'headers' => $this->headers(),
                'json' => array_merge(['uid' => $index], $options),
            ]);
            return json_decode((string)$res->getBody(), true) ?? [];
        } catch (RequestException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function indexDocuments(string $index, array $documents): array
    {
        try {
            $res = $this->http->post("indexes/{$index}/documents", [
                'headers' => $this->headers(),
                'json' => $documents,
            ]);
            return json_decode((string)$res->getBody(), true) ?? [];
        } catch (RequestException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function deleteDocument(string $index, $documentId): array
    {
        try {
            $res = $this->http->delete("indexes/{$index}/documents/{$documentId}", [
                'headers' => $this->headers(),
            ]);
            return json_decode((string)$res->getBody(), true) ?? [];
        } catch (RequestException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function search(string $index, string $query, array $options = []): array
    {
        try {
            $payload = array_merge(['q' => $query], $options);
            $res = $this->http->post("indexes/{$index}/search", [
                'headers' => $this->headers(),
                'json' => $payload,
            ]);
            return json_decode((string)$res->getBody(), true) ?? [];
        } catch (RequestException $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
