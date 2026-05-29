<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProviderRateLimiter
{
    /**
     * Attempt to consume one token for provider in current minute window.
     * Returns true if allowed, false if limit exceeded.
     */
    public function allow(string $provider): bool
    {
        $limit = $this->getLimitFor($provider);
        $key = $this->keyFor($provider);
        try {
            $count = Cache::increment($key);
            if ($count === null) {
                // Key probably didn't exist; initialize with 1 and expiry 70s
                Cache::put($key, 1, 70);
                $count = 1;
            }

            if ($count > $limit) {
                // rollback increment
                Cache::decrement($key);
                Log::info('Provider rate limit exceeded', ['provider' => $provider, 'limit' => $limit]);
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Rate limiter error, allowing by default', ['provider' => $provider, 'error' => $e->getMessage()]);
            return true;
        }
    }

    protected function getLimitFor(string $provider): int
    {
        $envKey = 'AI_RATE_LIMIT_' . strtoupper($provider);
        $limit = env($envKey, null);
        if ($limit === null) {
            $limit = env('AI_RATE_LIMIT_DEFAULT', 60);
        }
        return (int) $limit;
    }

    protected function keyFor(string $provider): string
    {
        $minute = date('YmdHi');
        return "ai_rate:{$provider}:{$minute}";
    }
}
