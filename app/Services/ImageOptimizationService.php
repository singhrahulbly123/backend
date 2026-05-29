<?php

namespace App\Services;

class ImageOptimizationService
{
    public function optimize(string $url, int $width = 0, int $quality = 75): string
    {
        $optimizer = config('images.optimizer', 'none');

        if ($optimizer === 'cloudflare') {
            return $this->cloudflareOptimize($url, $width, $quality);
        }

        if ($optimizer === 'imagekit') {
            return $this->imageKitOptimize($url, $width, $quality);
        }

        return $url;
    }

    protected function cloudflareOptimize(string $url, int $width, int $quality): string
    {
        $domain = config('images.cloudflare.proxy_domain');
        if (empty($domain)) {
            return $url;
        }

        $width = max(0, $width);
        $quality = max(1, min(100, $quality));
        $params = [];
        if ($width > 0) {
            $params[] = "width={$width}";
        }
        $params[] = "quality={$quality}";
        $params[] = 'format=auto';

        $path = ltrim($this->normalizeCloudflarePath($url, $domain), '/');
        return sprintf('https://%s/cdn-cgi/image/%s/%s', $domain, implode(',', $params), $path);
    }

    protected function normalizeCloudflarePath(string $url, string $domain): string
    {
        if (str_starts_with($url, '/')) {
            return $url;
        }

        $parsed = parse_url($url);
        if ($parsed === false || empty($parsed['host'])) {
            return ltrim($url, '/');
        }

        if ($parsed['host'] === $domain) {
            return ltrim($parsed['path'] ?? '', '/') . ($parsed['query'] ? '?'.$parsed['query'] : '');
        }

        return $url;
    }

    protected function imageKitOptimize(string $url, int $width, int $quality): string
    {
        $endpoint = config('images.imagekit.url_endpoint');
        if (empty($endpoint)) {
            return $url;
        }

        $endpoint = rtrim($endpoint, '/');
        $width = max(0, $width);
        $quality = max(1, min(100, $quality));
        $params = [];
        if ($width > 0) {
            $params[] = "w-{$width}";
        }
        $params[] = 'f-auto';
        $params[] = "q-{$quality}";

        $encodedUrl = rawurlencode($url);
        return sprintf('%s/tr:%s/%s', $endpoint, implode(',', $params), $encodedUrl);
    }
}
