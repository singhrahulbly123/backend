<?php

namespace App\Services;

use App\Models\DailyAiBrief;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramDigestService
{
    public function publishDailyBrief(?DailyAiBrief $brief = null, bool $dryRun = false): array
    {
        if (! $brief) {
            try {
                $brief = DailyAiBrief::published()->latest('published_at')->first();
            } catch (Throwable $exception) {
                if (! $dryRun) {
                    throw $exception;
                }

                $brief = $this->sampleBrief();
            }
        }

        if (! $brief) {
            return ['sent' => false, 'reason' => 'No published daily AI brief found.'];
        }

        $message = $this->buildMessage($brief);
        $token = config('services.telegram.bot_token');
        $channelId = config('services.telegram.channel_id');

        if ($dryRun || ! $token || ! $channelId) {
            return [
                'sent' => false,
                'dry_run' => true,
                'reason' => $dryRun ? 'Dry run requested.' : 'Telegram credentials are not configured.',
                'brief_id' => $brief->id,
                'message' => $message,
            ];
        }

        $response = Http::timeout(12)->post(rtrim(config('services.telegram.api_url'), '/') . "/bot{$token}/sendMessage", [
            'chat_id' => $channelId,
            'text' => $message,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => false,
        ]);

        if (! $response->successful()) {
            Log::warning('Telegram daily brief publish failed', [
                'brief_id' => $brief->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }

        return [
            'sent' => $response->successful(),
            'brief_id' => $brief->id,
            'status' => $response->status(),
            'telegram_response' => $response->json(),
        ];
    }

    public function buildMessage(DailyAiBrief $brief): string
    {
        $updates = collect($brief->key_updates ?? [])
            ->take(4)
            ->values()
            ->map(fn ($item, $index) => ($index + 1) . '. ' . e($item))
            ->implode("\n");

        $prompts = collect($brief->prompts ?? [])
            ->take(2)
            ->map(fn ($prompt) => '• ' . e($prompt))
            ->implode("\n");

        $url = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')), '/') . '/daily-ai-brief/' . $brief->slug;

        return trim(implode("\n\n", array_filter([
            '<b>' . e($brief->title) . '</b>',
            e($brief->summary ?? ''),
            $updates ? "<b>Top updates</b>\n{$updates}" : null,
            $brief->impact_india ? '<b>Global impact</b>' . "\n" . e($brief->impact_india) : null,
            $prompts ? "<b>Prompts</b>\n{$prompts}" : null,
            '<a href="' . e($url) . '">Read full brief</a>',
        ])));
    }

    protected function sampleBrief(): DailyAiBrief
    {
        return new DailyAiBrief([
            'title' => 'Sample Daily AI Brief',
            'slug' => 'sample-daily-ai-brief',
            'summary' => 'Global AI updates, business impact, tool of the day, and copy-ready prompts in one short digest.',
            'key_updates' => [
                'AI tools are becoming easier for global creators and students.',
                'Prompt workflows can save time in research, writing, and job preparation.',
                'Daily brief format is ready for Telegram distribution.',
            ],
            'impact_india' => 'Global readers can use practical AI workflows for learning, content, and small business growth.',
            'prompts' => [
                'Explain [TOPIC] in simple English with examples.',
                'Create 5 English reel hooks for [TOPIC].',
            ],
        ]);
    }
}
