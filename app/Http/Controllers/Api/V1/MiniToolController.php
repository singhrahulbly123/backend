<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AI\AiOrchestrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MiniToolController extends Controller
{
    public function __construct(private readonly AiOrchestrator $ai) {}

    public function generate(Request $request, string $tool): JsonResponse
    {
        $data = $request->validate([
            'topic' => ['required', 'string', 'max:500'],
            'tone' => ['nullable', 'string', 'max:100'],
            'audience' => ['nullable', 'string', 'max:100'],
            'language' => ['nullable', 'string', 'max:40'],
            'context' => ['nullable', 'string', 'max:3000'],
        ]);

        $definition = $this->definition($tool);
        if ($definition === null) {
            return response()->json(['message' => 'Mini tool not found.'], 404);
        }

        try {
            $raw = $this->ai->run(
                "mini_tool_{$tool}",
                $definition['system'],
                $this->buildPrompt($definition, $data),
                null,
                ['json' => true, 'temperature' => 0.8, 'max_tokens' => 1200]
            );

            $json = json_decode($raw, true);
            if (is_array($json) && isset($json['items']) && is_array($json['items'])) {
                return response()->json([
                    'tool' => $tool,
                    'items' => array_slice($json['items'], 0, $definition['limit']),
                    'tips' => $json['tips'] ?? $definition['tips'],
                    'source' => 'ai',
                ]);
            }
        } catch (\Throwable $e) {
            // Fall through to deterministic templates so the utility still works.
        }

        return response()->json([
            'tool' => $tool,
            'items' => $this->fallbackItems($tool, $data),
            'tips' => $definition['tips'],
            'source' => 'template',
        ]);
    }

    protected function definition(string $tool): ?array
    {
        return match ($tool) {
            'headline' => [
                'limit' => 12,
                'system' => 'You generate high-CTR but non-clickbait English news headlines for a global audience. Return JSON only: {"items":["..."],"tips":["..."]}.',
                'instruction' => 'Generate 12 headline options for a news/article page. Keep them helpful, clear, Discover-safe, and under 90 characters.',
                'tips' => ['Use clear benefit or impact.', 'Avoid fake urgency and misleading clickbait.', 'Put the main keyword early.'],
            ],
            'youtube-title' => [
                'limit' => 15,
                'system' => 'You generate YouTube titles in English for a global audience. Return JSON only: {"items":["..."],"tips":["..."]}.',
                'instruction' => 'Generate 15 YouTube title options with curiosity, clarity, and search intent. Avoid misleading claims.',
                'tips' => ['Use one strong curiosity gap.', 'Keep important words in the first half.', 'Avoid overpromising.'],
            ],
            'instagram-caption' => [
                'limit' => 10,
                'system' => 'You generate Instagram captions in English with hashtags for a global audience. Return JSON only: {"items":["..."],"tips":["..."]}.',
                'instruction' => 'Generate 10 Instagram captions with hook, short body, CTA, and 4-6 relevant hashtags.',
                'tips' => ['Start with a scroll-stopping first line.', 'Use one CTA.', 'Keep hashtags relevant.'],
            ],
            'resume-bullets' => [
                'limit' => 8,
                'system' => 'You improve resume bullets. Return JSON only: {"items":["..."],"tips":["..."]}.',
                'instruction' => 'Rewrite the input into 8 ATS-friendly resume bullets with action verbs, measurable impact, and concise wording.',
                'tips' => ['Start with action verbs.', 'Add numbers where possible.', 'Show business impact.'],
            ],
            default => null,
        };
    }

    protected function buildPrompt(array $definition, array $data): string
    {
        return implode("\n", array_filter([
            $definition['instruction'],
            'Topic/input: '.$data['topic'],
            'Tone: '.($data['tone'] ?? 'helpful and practical'),
            'Audience: '.($data['audience'] ?? 'global English readers'),
            'Language: '.($data['language'] ?? 'English'),
            'Do not generate Hindi, Hinglish, or Devanagari text.',
            ! empty($data['context']) ? 'Context: '.$data['context'] : null,
        ]));
    }

    protected function fallbackItems(string $tool, array $data): array
    {
        $topic = trim($data['topic']);
        $short = Str::limit($topic, 72, '');

        return match ($tool) {
            'headline' => [
                "{$short}: what changed and why it matters",
                "{$short}: the global impact explained",
                "{$short}: key points, timeline, and expert context",
                "{$short}: a simple English guide",
                "{$short}: benefits, risks, and what comes next",
                "{$short}: latest update and next steps",
                "{$short}: 5 important points",
                "{$short}: complete explainer",
                "{$short}: why it is trending now",
                "{$short}: facts, context, and analysis",
                "{$short}: quick guide",
                "{$short}: what readers should watch next",
            ],
            'youtube-title' => [
                "{$short} Explained Clearly",
                "{$short}: What Is Really Happening?",
                "{$short} Just Changed the Conversation",
                "{$short}: Full Breakdown",
                "{$short}: Simple Guide for Beginners",
                "{$short}: What Will Change?",
                "{$short}: Explained in 10 Minutes",
                "{$short}: The Hidden Impact",
                "{$short}: Complete Analysis",
                "{$short}: My Honest Take",
                "{$short}: What It Means for Students and Creators",
                "{$short}: The Full Story",
                "{$short}: Must-Know Update",
                "{$short} vs Reality",
                "{$short}: Step-by-Step Explanation",
            ],
            'instagram-caption' => [
                "{$short}\n\nIn simple terms: this update matters because it could directly affect users and teams.\n\nSave this for later.\n#AI #TechNews #Learning",
                "{$short}\n\nDo you think this change is useful or risky?\n\nComment your view.\n#AITools #Creators #Tech",
                "{$short}\n\n30-second takeaway: understand the context first, then decide what to do next.\n\nShare with a friend.\n#AIUpdate #Technology #News",
            ],
            'resume-bullets' => [
                "Improved {$short} by identifying bottlenecks, streamlining execution, and delivering measurable team impact.",
                "Led {$short} initiatives with cross-functional coordination, improving quality, speed, and stakeholder visibility.",
                "Optimized {$short} workflows by using data-driven prioritization and clear performance tracking.",
                "Created repeatable processes for {$short}, reducing manual effort and improving consistency.",
                "Collaborated with teams to execute {$short} goals while maintaining timelines and quality standards.",
                "Analyzed {$short} performance and converted insights into practical improvements.",
                "Managed {$short} tasks end-to-end, from planning to delivery and reporting.",
                "Supported {$short} outcomes through documentation, communication, and continuous improvement.",
            ],
            default => [],
        };
    }
}
