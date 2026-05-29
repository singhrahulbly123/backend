<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProviderUsage extends Model
{
    protected $fillable = [
        'provider',
        'operation',
        'tokens',
        'cost_inr',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'tokens' => 'integer',
        'cost_inr' => 'decimal:4',
    ];
}
