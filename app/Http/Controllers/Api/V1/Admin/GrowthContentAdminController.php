<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateGrowthReelJob;
use App\Models\AiTool;
use App\Models\DailyAiBrief;
use App\Models\PromptTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GrowthContentAdminController extends Controller
{
    public function tools(Request $request): JsonResponse
    {
        return response()->json(AiTool::query()->latest()->paginate((int) $request->get('per_page', 50)));
    }

    public function storeTool(Request $request): JsonResponse
    {
        $tool = AiTool::create($this->toolData($request));

        return response()->json(['data' => $tool], 201);
    }

    public function updateTool(Request $request, AiTool $tool): JsonResponse
    {
        $tool->update($this->toolData($request, true));

        return response()->json(['data' => $tool->fresh()]);
    }

    public function destroyTool(AiTool $tool): JsonResponse
    {
        $tool->delete();

        return response()->json(['message' => 'AI tool deleted.']);
    }

    public function generateToolReel(Request $request, AiTool $tool): JsonResponse
    {
        return $this->queueGrowthReel($request, 'ai_tool', $tool->id);
    }

    public function prompts(Request $request): JsonResponse
    {
        return response()->json(PromptTemplate::query()->latest()->paginate((int) $request->get('per_page', 50)));
    }

    public function storePrompt(Request $request): JsonResponse
    {
        $prompt = PromptTemplate::create($this->promptData($request));

        return response()->json(['data' => $prompt], 201);
    }

    public function updatePrompt(Request $request, PromptTemplate $promptTemplate): JsonResponse
    {
        $promptTemplate->update($this->promptData($request, true));

        return response()->json(['data' => $promptTemplate->fresh()]);
    }

    public function destroyPrompt(PromptTemplate $promptTemplate): JsonResponse
    {
        $promptTemplate->delete();

        return response()->json(['message' => 'Prompt deleted.']);
    }

    public function briefs(Request $request): JsonResponse
    {
        return response()->json(DailyAiBrief::query()->latest()->paginate((int) $request->get('per_page', 50)));
    }

    public function storeBrief(Request $request): JsonResponse
    {
        $brief = DailyAiBrief::create($this->briefData($request));

        return response()->json(['data' => $brief], 201);
    }

    public function updateBrief(Request $request, DailyAiBrief $dailyAiBrief): JsonResponse
    {
        $dailyAiBrief->update($this->briefData($request, true));

        return response()->json(['data' => $dailyAiBrief->fresh()]);
    }

    public function destroyBrief(DailyAiBrief $dailyAiBrief): JsonResponse
    {
        $dailyAiBrief->delete();

        return response()->json(['message' => 'Brief deleted.']);
    }

    public function generateBriefReel(Request $request, DailyAiBrief $dailyAiBrief): JsonResponse
    {
        return $this->queueGrowthReel($request, 'daily_brief', $dailyAiBrief->id);
    }

    public function reelStatus(string $job): JsonResponse
    {
        $statusPath = storage_path("app/public/videos/{$job}.status.json");
        if (! file_exists($statusPath)) {
            return response()->json(['message' => 'Reel status not found.'], 404);
        }

        $payload = json_decode(file_get_contents($statusPath), true);

        return response()->json(['data' => $payload]);
    }

    protected function queueGrowthReel(Request $request, string $sourceType, int $sourceId): JsonResponse
    {
        $data = $request->validate([
            'voice' => ['nullable', 'string', 'max:128'],
            'platform' => ['nullable', 'in:instagram_reel,youtube_short,facebook_reel,landscape'],
            'include_subtitles' => ['boolean'],
        ]);

        $jobId = 'reel_' . Str::random(12);
        $platform = $data['platform'] ?? 'instagram_reel';
        $voice = $data['voice'] ?? 'alloy';
        $includeSubtitles = $data['include_subtitles'] ?? true;

        Storage::disk('public')->put("videos/{$jobId}.status.json", json_encode([
            'job_id' => $jobId,
            'status' => 'queued',
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'platform' => $platform,
            'updated_at' => now()->toDateTimeString(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        GenerateGrowthReelJob::dispatch($jobId, $sourceType, $sourceId, $platform, $voice, $includeSubtitles, true);

        return response()->json(['data' => [
            'job_id' => $jobId,
            'status' => 'queued',
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'platform' => $platform,
        ]]);
    }

    protected function toolData(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $data = $request->validate([
            'name' => [$required, 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'category' => [$required, 'string', 'max:100'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'website_url' => ['nullable', 'string', 'max:1000'],
            'affiliate_url' => ['nullable', 'string', 'max:1000'],
            'pricing' => ['nullable', 'string', 'max:100'],
            'best_for' => ['nullable', 'array'],
            'pros' => ['nullable', 'array'],
            'cons' => ['nullable', 'array'],
            'alternatives' => ['nullable', 'array'],
            'use_cases' => ['nullable', 'array'],
            'faqs' => ['nullable', 'array'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string'],
            'rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'is_featured' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'published_at' => ['nullable', 'date'],
        ]);

        if (empty($data['slug']) && ! empty($data['name'])) {
            $data['slug'] = Str::slug($data['name']).'-'.Str::random(5);
        }

        return $data;
    }

    protected function promptData(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $data = $request->validate([
            'title' => [$required, 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'category' => [$required, 'string', 'max:100'],
            'audience' => ['nullable', 'string', 'max:100'],
            'language' => ['nullable', 'string', 'max:20'],
            'use_case' => ['nullable', 'string'],
            'prompt' => [$required, 'string'],
            'tags' => ['nullable', 'array'],
            'is_featured' => ['boolean'],
            'is_active' => ['boolean'],
            'published_at' => ['nullable', 'date'],
        ]);

        if (empty($data['slug']) && ! empty($data['title'])) {
            $data['slug'] = Str::slug($data['title']).'-'.Str::random(5);
        }

        return $data;
    }

    protected function briefData(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';
        $data = $request->validate([
            'title' => [$required, 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string'],
            'key_updates' => ['nullable', 'array'],
            'tool_of_day' => ['nullable', 'array'],
            'prompts' => ['nullable', 'array'],
            'impact_india' => ['nullable', 'string'],
            'cta_label' => ['nullable', 'string', 'max:255'],
            'cta_url' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'in:draft,published,archived'],
            'published_at' => ['nullable', 'date'],
        ]);

        if (empty($data['slug']) && ! empty($data['title'])) {
            $data['slug'] = Str::slug($data['title']).'-'.Str::random(5);
        }

        return $data;
    }
}
