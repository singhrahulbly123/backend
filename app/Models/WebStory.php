<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebStory extends Model
{
    protected $fillable = [
        'article_id', 'author_id', 'title', 'slug', 'locale', 'cover_image',
        'pages', 'status', 'is_ai_generated', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'pages' => 'array',
            'is_ai_generated' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
