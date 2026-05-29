<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\AiClient;

class AiChatTest extends TestCase
{
    use RefreshDatabase;

    public function test_ai_chat_endpoint_returns_mocked_response()
    {
        // Bind a simple mock AiClient into the container
        $this->instance(AiClient::class, new class {
            public function chat($messages, $options = [])
            {
                return [
                    'id' => 'mocked-response',
                    'choices' => [
                        ['message' => ['role' => 'assistant', 'content' => 'Mocked AI reply']]
                    ],
                ];
            }
        });

        $payload = [
            'messages' => [
                ['role' => 'user', 'content' => 'Hello AI']
            ],
            'options' => []
        ];

        $response = $this->postJson('/api/v1/ai/chat', $payload);

        $response->assertStatus(200);
        $response->assertJsonFragment(['id' => 'mocked-response']);
        $response->assertJsonPath('choices.0.message.content', 'Mocked AI reply');
    }
}
