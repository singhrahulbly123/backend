<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\WebStoriesService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Jobs\GenerateTtsJob;
use App\Jobs\GenerateVideoJob;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WebStoryPipelineController extends Controller
{
    protected WebStoriesService $service;

    public function __construct(WebStoriesService $service)
    {
        $this->service = $service;
    }

    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:255',
            'excerpt' => 'nullable|string',
            'ai_summary' => 'nullable|string',
            'slides' => 'nullable|array',
        ]);

        $story = $this->service->generate($data);

        return response()->json(['story' => $story]);
    }

    public function tts(Request $request, string $slug): JsonResponse
    {
        $data = $request->validate([
            'voice' => 'nullable|string|max:128',
            'text' => 'nullable|string',
        ]);

        $voice = $data['voice'] ?? 'alloy';
        $text = $data['text'] ?? "Generate audio for story: {$slug}";

        $jobId = 'tts_' . Str::random(12);
        Storage::disk('public')->put("tts/{$jobId}.status.json", json_encode([
            'job_id' => $jobId,
            'story_slug' => $slug,
            'status' => 'queued',
            'voice' => $voice,
            'updated_at' => now()->toDateTimeString(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        GenerateTtsJob::dispatch($jobId, $text, $voice);

        return response()->json(['tts' => [
            'job_id' => $jobId,
            'story_slug' => $slug,
            'status' => 'queued',
        ]]);
    }

    public function video(Request $request, string $slug): JsonResponse
    {
        $data = $request->validate([
            'audio_path' => 'required|string',
            'title' => 'nullable|string|max:255',
            'width' => 'nullable|integer|min:320|max:3840',
            'height' => 'nullable|integer|min:240|max:2160',
        ]);

        $jobId = 'video_' . Str::random(12);
        Storage::disk('public')->put("videos/{$jobId}.status.json", json_encode([
            'job_id' => $jobId,
            'story_slug' => $slug,
            'audio_path' => $data['audio_path'],
            'status' => 'queued',
            'updated_at' => now()->toDateTimeString(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        GenerateVideoJob::dispatch($jobId, $data['audio_path'], $slug, [
            'title' => $data['title'] ?? "Story video for {$slug}",
            'width' => $data['width'] ?? 1280,
            'height' => $data['height'] ?? 720,
        ]);

        return response()->json(['video' => [
            'job_id' => $jobId,
            'story_slug' => $slug,
            'status' => 'queued',
        ]]);
    }
}
