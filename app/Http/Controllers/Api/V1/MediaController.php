<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Services\ImageConversionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function __construct(private readonly ImageConversionService $conversion)
    {
    }

    public function upload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:jpg,jpeg,png,webp,gif,mp4'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $request->file('file');
        $path = $file->store('media/'.date('Y/m'), 'public');

        $variants = [];
        if (preg_match('/\.(jpe?g|png|webp)$/i', $path)) {
            $variants = $this->conversion->convertToOptimizedVariants('public', $path);
        }

        $media = Media::create([
            'user_id' => $request->user()->id,
            'disk' => 'public',
            'path' => $path,
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'alt_text' => $request->alt_text,
            'variants' => $variants,
        ]);

        return response()->json([
            'data' => $media,
            'url' => Storage::disk('public')->url($path),
            'variants' => $variants,
        ], 201);
    }

    public function destroy(Media $media): JsonResponse
    {
        Storage::disk($media->disk)->delete($media->path);
        $media->delete();

        return response()->json(['message' => 'Deleted.']);
    }
}
