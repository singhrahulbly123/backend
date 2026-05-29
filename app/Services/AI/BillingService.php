<?php

namespace App\Services\AI;

use App\Models\ProviderUsage;

class BillingService
{
    /**
     * Record provider usage, estimate cost based on tokens and env pricing.
     */
    public function recordUsage(string $provider, int $tokens = 0, array $meta = [], string $operation = null): ProviderUsage
    {
        $cost = $this->estimateCostInInr($provider, $tokens);

        return ProviderUsage::create([
            'provider' => $provider,
            'operation' => $operation,
            'tokens' => $tokens,
            'cost_inr' => $cost,
            'meta' => $meta,
        ]);
    }

    protected function estimateCostInInr(string $provider, int $tokens): float
    {
        // Price env is cost per 1000 tokens in INR (e.g., AI_COST_PER_1K_TOKENS_OPENAI=1.5)
        $envKey = 'AI_COST_PER_1K_TOKENS_' . strtoupper($provider);
        $per1k = (float) env($envKey, env('AI_COST_PER_1K_TOKENS_DEFAULT', 1.0));

        if ($tokens <= 0) return 0.0;

        $cost = ($tokens / 1000.0) * $per1k;
        return round($cost, 4);
    }
}
