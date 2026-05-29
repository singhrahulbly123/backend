<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LearningPath extends Model
{
    protected $fillable = [
        'title', 'slug', 'category', 'level', 'description', 'outcomes',
        'audience', 'duration_minutes', 'is_featured', 'status', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'outcomes' => 'array',
            'audience' => 'array',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (LearningPath $path) {
            $path->slug ??= Str::slug($path->title).'-'.Str::random(5);
        });
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(LearningLesson::class)->orderBy('sort_order');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')->where('published_at', '<=', now());
    }
}
