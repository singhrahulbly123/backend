<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AiComparison extends Model
{
    protected $fillable = [
        'title', 'slug', 'category', 'tool_a', 'tool_b', 'summary', 'winner',
        'best_for', 'scorecard', 'pros_cons', 'faqs', 'cta_label', 'cta_url',
        'is_featured', 'status', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'best_for' => 'array',
            'scorecard' => 'array',
            'pros_cons' => 'array',
            'faqs' => 'array',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AiComparison $comparison) {
            $comparison->slug ??= Str::slug($comparison->title).'-'.Str::random(5);
        });
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')->where('published_at', '<=', now());
    }
}
