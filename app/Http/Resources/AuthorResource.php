<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'display_name' => $this->display_name ?? $this->user->name,
            'slug' => $this->slug,
            'bio' => $this->bio,
            'avatar' => $this->user->avatar,
            'designation' => $this->user->designation,
            'is_verified' => $this->user->is_verified_author,
            'expertise_topics' => $this->credentials ?? $this->user->expertise_topics ?? [],
            'social_links' => [
                'twitter' => $this->twitter,
                'linkedin' => $this->linkedin,
            ],
            'article_count' => $this->user->articles()->count(),
        ];
    }
}
