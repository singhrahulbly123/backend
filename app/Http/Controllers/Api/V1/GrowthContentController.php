<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AiTool;
use App\Models\DailyAiBrief;
use App\Models\PromptTemplate;
use App\Services\DifferentiatorService;
use App\Services\WebStoriesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GrowthContentController extends Controller
{
    public function tools(Request $request): JsonResponse
    {
        $tools = AiTool::published()
            ->when($request->category, fn ($query, $category) => $query->where('category', $category))
            ->when($request->q, fn ($query, $q) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$q}%")
                ->orWhere('tagline', 'like', "%{$q}%")
                ->orWhere('description', 'like', "%{$q}%")
            ))
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->latest()
            ->paginate((int) $request->get('per_page', 18));

        return response()->json($tools);
    }

    public function tool(string $slug): JsonResponse
    {
        $tool = AiTool::published()->where('slug', $slug)->firstOrFail();
        $scores = app(DifferentiatorService::class)->toolScores($tool);

        $related = AiTool::published()
            ->where('id', '!=', $tool->id)
            ->where('category', $tool->category)
            ->limit(6)
            ->get();

        return response()->json(['data' => [...$tool->toArray(), ...$scores], 'related' => $related]);
    }

    public function toolStory(string $slug, WebStoriesService $stories): JsonResponse
    {
        $tool = AiTool::published()->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => $stories->generateFromTool($tool)]);
    }

    public function toolStories(Request $request, WebStoriesService $stories): JsonResponse
    {
        $tools = AiTool::published()
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->latest()
            ->limit((int) $request->get('limit', 12))
            ->get();

        return response()->json([
            'data' => $tools->map(fn (AiTool $tool) => $stories->generateFromTool($tool))->values(),
        ]);
    }

    public function toolFinder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'audience' => ['nullable', 'string', 'max:100'],
            'goal' => ['nullable', 'string', 'max:100'],
            'budget' => ['nullable', 'string', 'max:50'],
        ]);

        $audience = strtolower($data['audience'] ?? '');
        $goal = strtolower($data['goal'] ?? '');
        $budget = strtolower($data['budget'] ?? '');

        $tools = AiTool::published()->get()->map(function (AiTool $tool) use ($audience, $goal, $budget) {
            $haystack = strtolower(implode(' ', array_filter([
                $tool->name,
                $tool->category,
                $tool->tagline,
                $tool->description,
                $tool->pricing,
                implode(' ', $tool->best_for ?? []),
                implode(' ', $tool->pros ?? []),
            ])));

            $score = 40 + (($tool->rating ?? 4.5) * 8);
            $reasons = [];

            foreach ([$audience, $goal] as $signal) {
                if ($signal !== '' && str_contains($haystack, $signal)) {
                    $score += 18;
                    $reasons[] = "Matches {$signal}";
                }
            }

            if ($budget === 'free' && str_contains($haystack, 'free')) {
                $score += 12;
                $reasons[] = 'Has free option';
            } elseif ($budget === 'paid' && str_contains($haystack, 'paid')) {
                $score += 8;
                $reasons[] = 'Paid plan available for serious users';
            }

            if ($tool->is_featured) {
                $score += 8;
                $reasons[] = 'Featured by editors';
            }

            return [
                'tool' => $tool,
                'score' => min(100, round($score)),
                'reasons' => $reasons ?: ['Good all-round fit for AI productivity'],
            ];
        })->sortByDesc('score')->values()->take(6);

        if ($tools->isEmpty()) {
            $tools = collect($this->starterToolFinderResults($audience, $goal, $budget));
        }

        return response()->json([
            'recommendations' => $tools,
            'profile' => [
                'audience' => $data['audience'] ?? 'creator',
                'goal' => $data['goal'] ?? 'content',
                'budget' => $data['budget'] ?? 'free',
            ],
        ]);
    }

    public function prompts(Request $request): JsonResponse
    {
        $prompts = PromptTemplate::published()
            ->when($request->category, fn ($query, $category) => $query->where('category', $category))
            ->when($request->audience, fn ($query, $audience) => $query->where('audience', $audience))
            ->when($request->q, fn ($query, $q) => $query->where(fn ($inner) => $inner
                ->where('title', 'like', "%{$q}%")
                ->orWhere('use_case', 'like', "%{$q}%")
                ->orWhere('prompt', 'like', "%{$q}%")
            ))
            ->orderByDesc('is_featured')
            ->orderByDesc('copy_count')
            ->latest()
            ->paginate((int) $request->get('per_page', 24));

        return response()->json($prompts);
    }

    protected function starterToolFinderResults(string $audience, string $goal, string $budget): array
    {
        return [
            [
                'tool' => [
                    'id' => 1,
                    'name' => 'ChatGPT',
                    'slug' => 'chatgpt',
                    'category' => 'AI Writing',
                    'tagline' => 'Best all-round assistant for writing, learning, and content.',
                    'pricing' => 'Free + paid',
                    'best_for' => ['Students', 'Creators', 'Business'],
                ],
                'score' => 92,
                'reasons' => ['Strong general fit', 'Works well for English prompts', 'Useful for writing and learning'],
            ],
            [
                'tool' => [
                    'id' => 2,
                    'name' => 'Perplexity',
                    'slug' => 'perplexity',
                    'category' => 'AI Search',
                    'tagline' => 'Research answers with sources.',
                    'pricing' => 'Free + paid',
                    'best_for' => ['Research', 'News tracking'],
                ],
                'score' => 88,
                'reasons' => ['Great for research', 'Useful source citations', 'Good for comparison and news ideas'],
            ],
        ];
    }

    public function prompt(string $slug): JsonResponse
    {
        $prompt = PromptTemplate::published()->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => $prompt]);
    }

    public function recordPromptCopy(PromptTemplate $promptTemplate): JsonResponse
    {
        $promptTemplate->increment('copy_count');

        return response()->json(['copy_count' => $promptTemplate->copy_count + 1]);
    }

    public function briefs(Request $request): JsonResponse
    {
        $briefs = DailyAiBrief::published()
            ->latest('published_at')
            ->paginate((int) $request->get('per_page', 12));

        return response()->json($briefs);
    }

    public function latestBrief(): JsonResponse
    {
        $brief = DailyAiBrief::published()->latest('published_at')->firstOrFail();

        return response()->json(['data' => $brief]);
    }

    public function brief(string $slug): JsonResponse
    {
        $brief = DailyAiBrief::published()->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => $brief]);
    }

    public function personalizedFeed(Request $request, DifferentiatorService $differentiators): JsonResponse
    {
        $data = $request->validate([
            'role' => ['nullable', 'string', 'in:student,creator,job_seeker,business'],
            'limit' => ['nullable', 'integer', 'min:4', 'max:30'],
        ]);

        return response()->json($differentiators->personalizedFeed(
            $data['role'] ?? 'creator',
            (int) ($data['limit'] ?? 12)
        ));
    }

    public function latestVoiceBrief(DifferentiatorService $differentiators): JsonResponse
    {
        $brief = DailyAiBrief::published()->latest('published_at')->firstOrFail();

        return response()->json(['data' => $differentiators->voiceBrief($brief)]);
    }
}
