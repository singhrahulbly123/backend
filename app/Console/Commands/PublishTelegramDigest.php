<?php

namespace App\Console\Commands;

use App\Models\DailyAiBrief;
use App\Services\TelegramDigestService;
use Illuminate\Console\Command;

class PublishTelegramDigest extends Command
{
    protected $signature = 'digest:telegram {--slug=} {--dry-run}';

    protected $description = 'Publish the latest Daily AI Brief to the configured Telegram channel';

    public function handle(TelegramDigestService $service): int
    {
        $brief = $this->option('slug')
            ? DailyAiBrief::where('slug', $this->option('slug'))->first()
            : null;

        if ($this->option('slug') && ! $brief) {
            $this->error('Daily brief not found for slug: ' . $this->option('slug'));
            return self::FAILURE;
        }

        $result = $service->publishDailyBrief($brief, (bool) $this->option('dry-run'));

        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return ($result['sent'] ?? false) || ($result['dry_run'] ?? false)
            ? self::SUCCESS
            : self::FAILURE;
    }
}
