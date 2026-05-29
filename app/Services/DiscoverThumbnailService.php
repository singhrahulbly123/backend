<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class DiscoverThumbnailService
{
    public function generateFromFeaturedImage(?string $featuredImage, int $width = 1200, int $height = 675): ?string
    {
        if (empty($featuredImage)) {
            return null;
        }

        $disk = Storage::disk('public');
        $relativePath = ltrim(parse_url($featuredImage, PHP_URL_PATH) ?? $featuredImage, '/');

        if (! $disk->exists($relativePath)) {
            return null;
        }

        $extension = function_exists('imagewebp') || class_exists('Imagick') ? 'webp' : 'jpg';
        $targetPath = 'discover_thumbnails/' . pathinfo($relativePath, PATHINFO_FILENAME) . '.' . $extension;
        if ($disk->exists($targetPath)) {
            return $disk->url($targetPath);
        }

        $source = $disk->path($relativePath);
        $info = @getimagesize($source);
        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            return null;
        }

        $targetFullPath = $disk->path($targetPath);
        $targetDirectory = dirname($targetFullPath);
        if (! file_exists($targetDirectory)) {
            mkdir($targetDirectory, 0755, true);
        }

        if (class_exists('Imagick')) {
            try {
                $imagick = new \Imagick($source);
                $imagick->setImageColorspace(\Imagick::COLORSPACE_RGB);
                $imagick->cropThumbnailImage($width, $height);
                $imagick->setImageFormat('webp');
                $imagick->setImageCompressionQuality(82);
                $imagick->writeImage($targetFullPath);
                $imagick->clear();
                return $disk->url($targetPath);
            } catch (\Throwable) {
                // fallback to GD
            }
        }

        if (! extension_loaded('gd')) {
            return null;
        }

        $image = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
            IMAGETYPE_PNG => @imagecreatefrompng($source),
            IMAGETYPE_WEBP => @imagecreatefromwebp($source),
            default => null,
        };

        if (! $image) {
            return null;
        }

        [$sourceWidth, $sourceHeight] = [$info[0], $info[1]];
        $targetRatio = $width / $height;
        $sourceRatio = $sourceWidth / $sourceHeight;

        if ($sourceRatio > $targetRatio) {
            $cropHeight = $sourceHeight;
            $cropWidth = (int) round($sourceHeight * $targetRatio);
            $srcX = (int) round(($sourceWidth - $cropWidth) / 2);
            $srcY = 0;
        } else {
            $cropWidth = $sourceWidth;
            $cropHeight = (int) round($sourceWidth / $targetRatio);
            $srcX = 0;
            $srcY = (int) round(($sourceHeight - $cropHeight) / 2);
        }

        $thumbnail = imagecreatetruecolor($width, $height);
        if ($thumbnail === false) {
            imagedestroy($image);
            return null;
        }

        imagecopyresampled($thumbnail, $image, 0, 0, $srcX, $srcY, $width, $height, $cropWidth, $cropHeight);
        imagedestroy($image);

        $targetFullPath = $disk->path($targetPath);
        if (function_exists('imagewebp')) {
            $result = imagewebp($thumbnail, $targetFullPath, 82);
        } else {
            $result = imagejpeg($thumbnail, $targetFullPath, 82);
        }

        imagedestroy($thumbnail);

        return $result ? $disk->url($targetPath) : null;
    }
}
