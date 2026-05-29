<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HeadlineExperiment extends Model
{
    protected $fillable = [
        'article_id',
        'title',
        'description',
        'locale',
        'status',
        'winner_variant_id',
        'created_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(HeadlineVariant::class);
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(HeadlineVariant::class, 'winner_variant_id');
    }
}
