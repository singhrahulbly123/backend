<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Media extends Model
{
    protected $fillable = [
        'user_id', 'disk', 'path', 'filename', 'mime_type', 'size_bytes',
        'width', 'height', 'alt_text', 'variants',
    ];

    protected function casts(): array
    {
        return ['variants' => 'array'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
