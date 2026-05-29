<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Comment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    public function index(string $slug): JsonResponse
    {
        $article = Article::where('slug', $slug)->firstOrFail();

        $comments = $article->comments()
            ->where('status', 'approved')
            ->whereNull('parent_id')
            ->with('user:id,name,avatar')
            ->latest()
            ->paginate(30);

        return response()->json($comments);
    }

    public function store(string $slug, Request $request): JsonResponse
    {
        $article = Article::where('slug', $slug)->firstOrFail();

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'author_name' => ['required_without:user', 'string', 'max:100'],
            'author_email' => ['nullable', 'email'],
        ]);

        $comment = $article->comments()->create([
            ...$data,
            'user_id' => $request->user()?->id,
            'status' => 'pending',
        ]);

        return response()->json(['data' => $comment], 201);
    }
}
