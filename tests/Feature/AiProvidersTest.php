<?php

namespace Tests\Feature;

use Tests\TestCase;

class AiProvidersTest extends TestCase
{
    #[\PHPUnit\Framework\Attributes\Test]
    public function it_runs_ai_orchestrator_with_mock()
    {
        // This test ensures the orchestrator flow can be exercised with mocks.
        // It does not call external APIs in CI unless keys are provided.

        $response = $this->postJson('/api/v1/ai/chat', [
            'messages' => [
                ['role' => 'user', 'content' => 'Hello, test']
            ],
        ]);

        $response->assertStatus(200);
        $this->assertArrayHasKey('choices', $response->json());
    }
}
