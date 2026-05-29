<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrendingTopic extends Model
{
    protected $fillable = [
        'title', 'source', 'locale', 'trend_score', 'seo_value', 'virality_score',
        'rpm_potential', 'metadata', 'status', 'article_id', 'detected_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'detected_at' => 'datetime',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
