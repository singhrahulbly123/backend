<?php

use App\Services\TrendDetectorService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('trends:detect {--sources=*}', function () {
    $sources = $this->option('sources');
    $detector = app(TrendDetectorService::class);
    $topics = $detector->detect($sources ?: []);

    if (empty($topics)) {
        $this->warn('No trend topics were detected.');
        return;
    }

    $this->info('Detected trend topics:');
    foreach ($topics as $topic) {
        $this->line('- ' . ($topic['headline'] ?? $topic['title'] ?? 'Untitled'));
    }
})->purpose('Detect trending topics from configured feed sources');

Schedule::command('digest:telegram')->dailyAt('08:30')->withoutOverlapping();
