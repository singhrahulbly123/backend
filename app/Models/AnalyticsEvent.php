<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AnalyticsEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'event_type', 'trackable_type', 'trackable_id', 'session_id', 'user_agent',
        'ip_hash', 'referrer', 'utm_source', 'utm_medium', 'utm_campaign',
        'metadata', 'revenue_inr', 'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'revenue_inr' => 'decimal:4',
            'recorded_at' => 'datetime',
        ];
    }

    public function trackable(): MorphTo
    {
        return $this->morphTo();
    }
}
