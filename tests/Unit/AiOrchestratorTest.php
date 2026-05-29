<?php

namespace Tests\Unit;

use App\Services\AI\AiOrchestrator;
use App\Services\AI\OpenAiProvider;
use App\Services\AI\GeminiProvider;
use App\Services\AI\GroqProvider;
use App\Services\AI\ProviderRateLimiter;
use App\Services\AI\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_uses_first_available_provider()
    {
        $openAi = $this->createMock(OpenAiProvider::class);
        $gemini = $this->createMock(GeminiProvider::class);
        $groq = $this->createMock(GroqProvider::class);
        // Claude removed from orchestrator tests

        $rateLimiter = $this->createMock(ProviderRateLimiter::class);
        $billing = $this->createMock(BillingService::class);

        // Rate limiter allows everything
        $rateLimiter->method('allow')->willReturn(true);

        $expected = ['content' => 'ok', 'usage' => ['total_tokens' => 5]];

        // Configure OpenAI mock to return expected response
        $openAi->method('chat')->willReturn($expected);

        $orchestrator = new AiOrchestrator($openAi, $gemini, $groq, $rateLimiter, $billing);

        $result = $orchestrator->chat([['role' => 'user', 'content' => 'hi']]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('content', $result);
        $this->assertEquals('ok', $result['content']);
    }

    public function test_run_falls_back_to_next_provider_when_first_provider_fails()
    {
        $openAi = $this->createMock(OpenAiProvider::class);
        $gemini = $this->createMock(GeminiProvider::class);
        $groq = $this->createMock(GroqProvider::class);

        $rateLimiter = $this->createMock(ProviderRateLimiter::class);
        $billing = $this->createMock(BillingService::class);

        $rateLimiter->method('allow')->willReturn(true);

        $openAi->method('chat')->willReturn(['error' => 'openai_unavailable']);
        $gemini->method('chat')->willReturn(['provider' => 'gemini', 'content' => 'fallback response', 'usage' => ['total_tokens' => 10]]);

        $billing->expects($this->once())
            ->method('recordUsage')
            ->with('gemini', 10, $this->arrayHasKey('provider'), 'generate_draft');

        $orchestrator = new AiOrchestrator($openAi, $gemini, $groq, $rateLimiter, $billing);

        $result = $orchestrator->run('generate_draft', 'system prompt', 'user prompt', 'openai', ['json' => true]);

        $this->assertEquals('fallback response', $result);
    }
}
