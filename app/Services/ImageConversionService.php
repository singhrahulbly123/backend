<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class ImageConversionService
{
    public function convertToOptimizedVariants(string $disk, string $path): array
    {
        $storage = Storage::disk($disk);
        if (! $storage->exists($path)) {
            return [];
        }

        $fullPath = $storage->path($path);
        $info = @getimagesize($fullPath);
        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            return [];
        }

        $variants = [];
        if ($converted = $this->createVariant($fullPath, $storage, $path, 'webp', $info[2])) {
            $variants['webp'] = $converted;
        }

        if ($converted = $this->createVariant($fullPath, $storage, $path, 'avif', $info[2])) {
            $variants['avif'] = $converted;
        }

        return $variants;
    }

    protected function createVariant(string $source, $storage, string $path, string $format, int $sourceType): ?string
    {
        $targetPath = preg_replace('/\.[^.]+$/', ".{$format}", $path);
        if ($targetPath === $path) {
            $targetPath = "{$path}.{$format}";
        }

        if ($storage->exists($targetPath)) {
            return $storage->url($targetPath);
        }

        if (class_exists('Imagick')) {
            try {
                $imagick = new \Imagick($source);
                $imagick->setImageFormat($format);
                $imagick->setImageCompressionQuality(80);
                $imagick->writeImage($storage->path($targetPath));
                $imagick->clear();
                return $storage->url($targetPath);
            } catch (\Throwable) {
                // ignore and attempt GD fallback
            }
        }

        if (! extension_loaded('gd')) {
            return null;
        }

        $image = match ($sourceType) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($source),
            IMAGETYPE_PNG => @imagecreatefrompng($source),
            IMAGETYPE_WEBP => @imagecreatefromwebp($source),
            default => null,
        };

        if (! $image) {
            return null;
        }

        $variantFullPath = $storage->path($targetPath);
        $result = false;
        if ($format === 'webp' && function_exists('imagewebp')) {
            $result = imagewebp($image, $variantFullPath, 80);
        }

        if ($format === 'avif' && function_exists('imageavif')) {
            $result = imageavif($image, $variantFullPath, 50);
        }

        imagedestroy($image);

        return $result ? $storage->url($targetPath) : null;
    }
}
