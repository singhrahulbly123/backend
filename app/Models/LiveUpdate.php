<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LiveUpdate extends Model
{
    protected $fillable = [
        'article_id', 'user_id', 'headline', 'content', 'is_breaking', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_breaking' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
