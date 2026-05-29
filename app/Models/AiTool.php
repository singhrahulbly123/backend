<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AiTool extends Model
{
    protected $fillable = [
        'name', 'slug', 'category', 'tagline', 'description', 'website_url',
        'affiliate_url', 'pricing', 'best_for', 'pros', 'cons', 'alternatives',
        'use_cases', 'faqs', 'seo_title', 'seo_description',
        'rating', 'trust_score', 'trust_breakdown', 'opportunity_score',
        'opportunity_summary', 'audience_roles', 'is_featured', 'is_active',
        'sort_order', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'best_for' => 'array',
            'pros' => 'array',
            'cons' => 'array',
            'alternatives' => 'array',
            'use_cases' => 'array',
            'faqs' => 'array',
            'trust_breakdown' => 'array',
            'audience_roles' => 'array',
            'trust_score' => 'integer',
            'opportunity_score' => 'integer',
            'rating' => 'float',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AiTool $tool) {
            $tool->slug ??= Str::slug($tool->name).'-'.Str::random(5);
        });
    }

    public function scopePublished($query)
    {
        return $query->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            });
    }
}
