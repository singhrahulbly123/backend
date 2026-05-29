<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PromptTemplate extends Model
{
    protected $fillable = [
        'title', 'slug', 'category', 'audience', 'language', 'use_case',
        'prompt', 'tags', 'is_featured', 'is_active', 'copy_count', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PromptTemplate $template) {
            $template->slug ??= Str::slug($template->title).'-'.Str::random(5);
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
