<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentQualityReport extends Model
{
    protected $fillable = [
        'article_id', 'plagiarism_score', 'hallucination_risk', 'spam_score',
        'readability_score', 'seo_score', 'fact_checks', 'recommendations',
    ];

    protected function casts(): array
    {
        return [
            'fact_checks' => 'array',
            'recommendations' => 'array',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
