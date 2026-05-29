<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DailyAiBrief extends Model
{
    protected $fillable = [
        'title', 'slug', 'summary', 'key_updates', 'tool_of_day', 'prompts',
        'impact_india', 'voice_script', 'voice_audio_url', 'voice_duration_seconds',
        'cta_label', 'cta_url', 'status', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'key_updates' => 'array',
            'tool_of_day' => 'array',
            'prompts' => 'array',
            'voice_duration_seconds' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (DailyAiBrief $brief) {
            $brief->slug ??= Str::slug($brief->title).'-'.Str::random(5);
        });
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')->where('published_at', '<=', now());
    }
}
