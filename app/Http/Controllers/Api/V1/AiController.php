<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AiClient;
use App\Services\AI\NewsAiService;
use App\Services\HeadlineScorerService;
use App\Models\Article;
use Illuminate\Http\Request;

class AiController extends Controller
{
    public function generateArticleSummary(Request $request, Article $article, NewsAiService $newsAi)
    {
        $this->middleware('auth:sanctum');

        $context = [
            'topic' => $article->title,
            'locale' => $article->locale ?? 'en',
            'sources' => [],
            'content_type' => 'summary',
            'body' => $article->body,
        ];

        $draft = $newsAi->generateDraft($context, $request->user()?->id ?? null);

        $summary = $draft['ai_summary'] ?? ($draft['excerpt'] ?? null);

        if ($summary) {
            $article->ai_summary = $summary;
            $article->save();
        }

        return response()->json(['summary' => $summary, 'draft' => $draft]);
    }

    public function scoreHeadlines(Request $request, Article $article, HeadlineScorerService $scorer)
    {
        $data = $request->validate([
            'headlines' => ['required', 'array', 'min:2'],
            'headlines.*' => ['required', 'string', 'max:500'],
            'locale' => ['nullable', 'string', 'max:10'],
        ]);

        $locale = $data['locale'] ?? $article->locale ?? 'en';
        $context = trim(implode("\n", array_filter([$article->title, $article->excerpt, strip_tags($article->body ?? '')])));

        $scores = $scorer->score($data['headlines'], $context, $locale);

        return response()->json(['scores' => $scores]);
    }

    public function chat(Request $request)
    {
        $data = $request->validate([
            'messages' => 'required|array',
            'options' => 'nullable|array',
        ]);

        $messages = $data['messages'];
        $options = $data['options'] ?? [];

        $result = app(AiClient::class)->chat($messages, $options);

        return response()->json($result);
    }
}
