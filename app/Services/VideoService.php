<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class VideoService
{
    public function __construct(private readonly SecretsManager $secrets) {}

    public function generateFromAudio(string $audioPath, string $storySlug, string $jobId, array $options = []): ?string
    {
        $sourcePath = storage_path('app/' . ltrim($audioPath, '/'));
        if (!file_exists($sourcePath)) {
            Log::warning('VideoService cannot generate video because audio file is missing', ['audio' => $sourcePath]);
            return null;
        }

        $videoPath = "public/videos/{$jobId}.mp4";
        $targetPath = storage_path('app/' . $videoPath);
        $targetDir = dirname($targetPath);

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        if ($this->hasFfmpeg()) {
            $imagePath = storage_path("app/public/videos/{$jobId}.png");
            $this->createPlaceholderFrame($imagePath, $options['width'] ?? 1280, $options['height'] ?? 720, $options['title'] ?? 'AI News Story');

            $subtitleFilter = '';
            if (!empty($options['subtitle_path'])) {
                $subtitlePath = storage_path('app/' . ltrim($options['subtitle_path'], '/'));
                if (file_exists($subtitlePath)) {
                    $subtitleFilter = ' -vf subtitles=' . escapeshellarg($subtitlePath);
                }
            }

            $cmd = sprintf(
                '%s -y -loop 1 -i %s -i %s%s -c:v libx264 -tune stillimage -c:a aac -b:a 128k -pix_fmt yuv420p -shortest %s 2>&1',
                escapeshellcmd($this->ffmpegBinary()),
                escapeshellarg($imagePath),
                escapeshellarg($sourcePath),
                $subtitleFilter,
                escapeshellarg($targetPath)
            );

            exec($cmd, $output, $exitCode);
            if ($exitCode === 0 && file_exists($targetPath)) {
                return $videoPath;
            }

            Log::warning('VideoService ffmpeg generation failed', ['command' => $cmd, 'output' => $output, 'exit' => $exitCode]);
        }

        $stub = json_encode([
            'job_id' => $jobId,
            'story_slug' => $storySlug,
            'audio' => $audioPath,
            'note' => 'FFmpeg not available or failed; saved placeholder stub instead.',
            'generated_at' => now()->toDateTimeString(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        Storage::disk('public')->put("videos/{$jobId}.json", $stub);
        return "public/videos/{$jobId}.json";
    }

    public function generateStoryboardStub(string $jobId, array $payload): string
    {
        $stub = array_merge([
            'job_id' => $jobId,
            'kind' => 'short_video_storyboard',
            'generated_at' => now()->toDateTimeString(),
        ], $payload);

        Storage::disk('public')->put("videos/{$jobId}.json", json_encode($stub, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return "public/videos/{$jobId}.json";
    }

    protected function hasFfmpeg(): bool
    {
        $binary = $this->ffmpegBinary();
        if (! $binary) {
            return false;
        }

        exec(escapeshellcmd($binary) . ' -version 2>&1', $output, $exitCode);
        return $exitCode === 0;
    }

    protected function ffmpegBinary(): ?string
    {
        return $this->secrets->get('FFMPEG_PATH', env('FFMPEG_PATH', 'ffmpeg'));
    }

    protected function createPlaceholderFrame(string $path, int $width, int $height, string $text): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            file_put_contents($path, '');
            return;
        }

        $image = imagecreatetruecolor($width, $height);
        $background = imagecolorallocate($image, 0, 0, 0);
        $textColor = imagecolorallocate($image, 255, 255, 255);
        imagefilledrectangle($image, 0, 0, $width, $height, $background);
        imagestring($image, 5, 30, (int) ($height / 2 - 10), $text, $textColor);
        imagepng($image, $path);
        imagedestroy($image);
    }
}
