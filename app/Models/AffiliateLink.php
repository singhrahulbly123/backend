<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AffiliateLink extends Model
{
    protected $fillable = [
        'name', 'slug', 'category', 'description', 'destination_url', 'tracking_code',
        'commission_rate', 'network', 'comparison_data', 'is_active', 'clicks_count', 'conversions_count',
    ];

    protected function casts(): array
    {
        return [
            'comparison_data' => 'array',
            'is_active' => 'boolean',
            'commission_rate' => 'decimal:2',
        ];
    }

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_affiliate')->withPivot('position');
    }
}
