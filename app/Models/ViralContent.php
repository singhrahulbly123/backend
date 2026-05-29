<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ViralContent extends Model
{
    protected $fillable = [
        'article_id', 'platform', 'content_type', 'script', 'assets', 'status', 'external_url',
    ];

    protected function casts(): array
    {
        return ['assets' => 'array'];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
