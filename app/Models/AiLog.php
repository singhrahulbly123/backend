<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AiLog extends Model
{
    protected $fillable = [
        'provider', 'operation', 'loggable_type', 'loggable_id', 'user_id',
        'request_payload', 'response_payload', 'tokens_used', 'cost_usd',
        'duration_ms', 'status', 'error_message',
    ];

    protected $casts = [
        'request_payload' => 'array',
        'response_payload' => 'array',
        'cost_usd' => 'decimal:6',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }
}
