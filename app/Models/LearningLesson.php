<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LearningLesson extends Model
{
    protected $fillable = [
        'learning_path_id', 'title', 'slug', 'summary', 'content',
        'action_steps', 'resources', 'sort_order', 'duration_minutes',
        'is_free', 'status', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'action_steps' => 'array',
            'resources' => 'array',
            'is_free' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (LearningLesson $lesson) {
            $lesson->slug ??= Str::slug($lesson->title).'-'.Str::random(5);
        });
    }

    public function learningPath(): BelongsTo
    {
        return $this->belongsTo(LearningPath::class);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')->where('published_at', '<=', now());
    }
}
