<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthorProfile extends Model
{
    protected $fillable = [
        'user_id', 'display_name', 'slug', 'bio', 'twitter', 'linkedin', 'credentials', 'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'array',
            'is_featured' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
