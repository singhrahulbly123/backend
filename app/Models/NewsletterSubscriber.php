<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterSubscriber extends Model
{
    protected $fillable = ['email', 'name', 'segment', 'interests', 'status', 'subscribed_at'];

    protected function casts(): array
    {
        return [
            'interests' => 'array',
            'subscribed_at' => 'datetime',
        ];
    }
}
