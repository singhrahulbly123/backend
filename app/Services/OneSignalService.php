<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OneSignalService
{
    public function sendNotification(string $title, string $message, array $topics = [], ?string $url = null): array
    {
        $body = [
            'app_id' => config('services.onesignal.app_id'),
            'headings' => ['en' => $title, 'hi' => $title],
            'contents' => ['en' => $message, 'hi' => $message],
            'url' => $url ? url($url) : url('/'),
            'included_segments' => ['Subscribed Users'],
        ];

        if (! empty($topics)) {
            $filters = [];
            foreach ($topics as $index => $topic) {
                if ($index > 0) {
                    $filters[] = ['operator' => 'OR'];
                }
                $filters[] = [
                    'field' => 'tag',
                    'key' => "topic_{$topic}",
                    'relation' => '=',
                    'value' => 'true',
                ];
            }
            $body['filters'] = $filters;
            unset($body['included_segments']);
        }

        $response = Http::withHeaders([
            'Authorization' => 'Basic '.config('services.onesignal.api_key'),
            'Content-Type' => 'application/json',
        ])->post(config('services.onesignal.rest_api_url').'/notifications', $body);

        return $response->json();
    }

    public function tagPlayer(string $playerId, array $tags): array
    {
        $body = [
            'app_id' => config('services.onesignal.app_id'),
            'tags' => $tags,
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Basic '.config('services.onesignal.api_key'),
            'Content-Type' => 'application/json',
        ])->put(config('services.onesignal.rest_api_url')."/players/{$playerId}", $body);

        return $response->json();
    }
}
