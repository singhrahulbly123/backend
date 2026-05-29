<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'segment' => ['nullable', 'string', 'max:100'],
            'interests' => ['nullable', 'array'],
        ]);

        $subscriber = NewsletterSubscriber::updateOrCreate(
            ['email' => strtolower($data['email'])],
            [
                'name' => $data['name'] ?? null,
                'segment' => $data['segment'] ?? 'daily_ai_brief',
                'interests' => $data['interests'] ?? ['ai_news', 'tools', 'learning'],
                'status' => 'subscribed',
                'subscribed_at' => now(),
            ]
        );

        return response()->json(['message' => 'Subscribed successfully.', 'subscriber' => $subscriber]);
    }
}
