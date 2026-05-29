<?php

namespace App\Services;

use App\Models\AiTool;
use Illuminate\Support\Str;

class WebStoriesService
{
    /**
     * Generate a web story structure from provided payload or article id.
     * This is a lightweight skeleton — replace with AI and templating later.
     */
    public function generate(array $payload): array
    {
        $title = $payload['title'] ?? ('Story ' . Str::random(6));
        $slides = $payload['slides'] ?? [
            ['type' => 'cover', 'text' => $payload['excerpt'] ?? 'Summary goes here'],
            ['type' => 'text', 'text' => $payload['ai_summary'] ?? 'Generated summary'],
        ];

        $slug = Str::slug($title) . '-' . Str::random(6);

        return [
            'slug' => $slug,
            'title' => $title,
            'slides' => $slides,
            'generated_at' => now()->toDateTimeString(),
        ];
    }

    public function generateFromTool(AiTool $tool): array
    {
        $slides = [
            [
                'type' => 'cover',
                'title' => $tool->name,
                'text' => $tool->tagline ?: 'AI tool review for global creators, students, and teams.',
                'badge' => $tool->category,
            ],
            [
                'type' => 'verdict',
                'title' => 'Quick verdict',
                'text' => $tool->description ? Str::limit($tool->description, 170) : 'A practical AI tool worth testing for everyday productivity.',
                'badge' => ($tool->rating ?? 4.5) . '/5',
            ],
            [
                'type' => 'best_for',
                'title' => 'Best for',
                'items' => $this->fallbackItems($tool->best_for ?? [], ['Students', 'Creators', 'Small teams']),
            ],
            [
                'type' => 'use_cases',
                'title' => 'Use cases',
                'items' => $this->fallbackItems($tool->use_cases ?? [], ['Write faster', 'Research ideas', 'Create reusable workflows']),
            ],
            [
                'type' => 'pros_cons',
                'title' => 'Pros and cons',
                'items' => [
                    'Pro: ' . ($tool->pros[0] ?? 'Useful for daily AI workflows'),
                    'Pro: ' . ($tool->pros[1] ?? 'Beginner friendly enough to test quickly'),
                    'Watch: ' . ($tool->cons[0] ?? 'Verify important facts before publishing'),
                ],
            ],
            [
                'type' => 'pricing',
                'title' => 'Pricing',
                'text' => $tool->pricing ?: 'Pricing varies. Check the official page before upgrading.',
            ],
            [
                'type' => 'alternatives',
                'title' => 'Alternatives',
                'items' => $this->fallbackItems($tool->alternatives ?? [], ['ChatGPT', 'Gemini', 'Perplexity']),
            ],
            [
                'type' => 'cta',
                'title' => 'Read full review',
                'text' => 'Compare pricing, pros, cons, and global use cases before choosing.',
                'cta_label' => 'Open review',
                'cta_url' => '/ai-tools/' . $tool->slug,
            ],
        ];

        return [
            'slug' => 'tool-story-' . $tool->slug,
            'title' => $tool->name . ' AI Tool Story',
            'cover_image' => null,
            'locale' => 'en',
            'pages' => $slides,
            'seo' => [
                'title' => $tool->name . ' AI Tool Web Story',
                'description' => $tool->tagline ?: $tool->seo_description,
                'canonical_path' => '/ai-tools/' . $tool->slug . '/story',
            ],
            'source' => [
                'type' => 'ai_tool',
                'id' => $tool->id,
                'slug' => $tool->slug,
            ],
            'generated_at' => now()->toDateTimeString(),
        ];
    }

    protected function fallbackItems(array $items, array $fallback): array
    {
        $items = array_values(array_filter($items));
        return array_slice($items ?: $fallback, 0, 4);
    }

    /**
     * Request TTS generation for a story. Returns a job stub.
     */
    public function generateTts(string $storySlug, string $voice = 'alloy'): array
    {
        // In a real pipeline, enqueue a job to produce TTS and store the result in storage.
        $jobId = 'tts_' . Str::random(12);
        return [
            'job_id' => $jobId,
            'story_slug' => $storySlug,
            'voice' => $voice,
            'status' => 'queued',
            'queued_at' => now()->toDateTimeString(),
        ];
    }
}
