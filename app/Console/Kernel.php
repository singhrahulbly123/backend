<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Run AI pipeline hourly to detect trends and create review drafts
        $schedule->command('ai:run-pipeline --limit=5')->hourly();
        $schedule->command('digest:telegram')->dailyAt('08:30')->withoutOverlapping();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
    }
}
