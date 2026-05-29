<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeadlineVariant extends Model
{
    protected $fillable = [
        'headline_experiment_id',
        'headline',
        'score',
        'reason',
        'impressions_count',
        'clicks_count',
        'ctr',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'impressions_count' => 'integer',
            'clicks_count' => 'integer',
            'ctr' => 'decimal:2',
        ];
    }

    public function experiment(): BelongsTo
    {
        return $this->belongsTo(HeadlineExperiment::class, 'headline_experiment_id');
    }
}
