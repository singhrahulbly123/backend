<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Services\OneSignalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public const TOPICS = [
        'global',
        'breaking',
        'trending',
        'ai_tools',
        'ai_learning',
        'ai_jobs',
        'daily_brief',
        'prompts',
    ];

    public function __construct(private readonly OneSignalService $oneSignal)
    {
    }

    public function subscribe(Request $request): JsonResponse
    {
        $data = $request->validate([
            'player_id' => ['required', 'string'],
            'topics' => ['nullable', 'array'],
            'topics.*' => ['string'],
            'locale' => ['nullable', 'string', 'max:10'],
        ]);

        $selectedTopics = array_values(array_filter($data['topics'] ?? []));
        $tags = $this->topicTags($selectedTopics);

        $this->oneSignal->tagPlayer($data['player_id'], $tags);

        $subscription = PushSubscription::updateOrCreate(
            ['endpoint' => $data['player_id']],
            [
                'public_key' => '',
                'auth_token' => '',
                'locale' => $data['locale'] ?? 'en',
                'topics' => $selectedTopics,
            ]
        );

        return response()->json(['subscribed' => true, 'subscription' => $subscription]);
    }

    public function topics(): JsonResponse
    {
        return response()->json([
            'topics' => [
                ['value' => 'global', 'label' => 'Global AI News', 'description' => 'Major AI updates and platform announcements.'],
                ['value' => 'breaking', 'label' => 'Breaking News', 'description' => 'Urgent AI and tech headlines.'],
                ['value' => 'trending', 'label' => 'Trending Alerts', 'description' => 'Stories gaining fast interest.'],
                ['value' => 'ai_tools', 'label' => 'AI Tools', 'description' => 'New tools, reviews, comparisons, and deals.'],
                ['value' => 'ai_learning', 'label' => 'AI Learning', 'description' => 'Prompt lessons, courses, and skill guides.'],
                ['value' => 'ai_jobs', 'label' => 'AI Jobs & Skills', 'description' => 'Career roadmaps, projects, and job-ready skills.'],
                ['value' => 'daily_brief', 'label' => 'Daily AI Brief', 'description' => 'Daily digest reminders.'],
                ['value' => 'prompts', 'label' => 'Prompts', 'description' => 'New prompt templates and mini tools.'],
            ],
        ]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'player_id' => ['required', 'string'],
            'topics' => ['required', 'array', 'min:1'],
            'topics.*' => ['string'],
            'locale' => ['nullable', 'string', 'max:10'],
        ]);

        $selectedTopics = array_values(array_intersect(self::TOPICS, array_filter($data['topics'])));
        $tags = $this->topicTags($selectedTopics);

        $this->oneSignal->tagPlayer($data['player_id'], $tags);

        $subscription = PushSubscription::updateOrCreate(
            ['endpoint' => $data['player_id']],
            [
                'public_key' => '',
                'auth_token' => '',
                'locale' => $data['locale'] ?? 'en',
                'topics' => $selectedTopics,
            ]
        );

        return response()->json(['updated' => true, 'subscription' => $subscription, 'topics' => $selectedTopics]);
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string'],
            'message' => ['required', 'string'],
            'topics' => ['nullable', 'array'],
            'topics.*' => ['string'],
            'url' => ['nullable', 'string'],
        ]);

        $result = $this->oneSignal->sendNotification($data['title'], $data['message'], $data['topics'] ?? [], $data['url'] ?? '/');

        return response()->json(['sent' => true, 'result' => $result]);
    }

    protected function topicTags(array $selectedTopics): array
    {
        $tags = [];
        foreach (self::TOPICS as $topic) {
            $tags["topic_{$topic}"] = in_array($topic, $selectedTopics, true) ? 'true' : 'false';
        }

        return $tags;
    }
}
