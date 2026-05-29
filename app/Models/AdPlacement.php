<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdPlacement extends Model
{
    protected $fillable = [
        'name', 'slot_key', 'page_type', 'ad_format', 'reserved_width', 'reserved_height',
        'revenue_channel', 'ad_code', 'lazy_load', 'is_active', 'priority',
    ];

    protected function casts(): array
    {
        return [
            'reserved_width' => 'integer',
            'reserved_height' => 'integer',
            'lazy_load' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
