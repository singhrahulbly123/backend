<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AiSkillGuide extends Model
{
    protected $fillable = [
        'title', 'slug', 'category', 'career_stage', 'summary', 'body',
        'skills', 'tools', 'projects', 'roadmap', 'faqs',
        'is_featured', 'status', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'skills' => 'array',
            'tools' => 'array',
            'projects' => 'array',
            'roadmap' => 'array',
            'faqs' => 'array',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AiSkillGuide $guide) {
            $guide->slug ??= Str::slug($guide->title).'-'.Str::random(5);
        });
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')->where('published_at', '<=', now());
    }
}
