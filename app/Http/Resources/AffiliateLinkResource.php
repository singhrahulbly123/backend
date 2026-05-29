<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AffiliateLinkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'category' => $this->category,
            'description' => $this->description,
            'tracking_url' => url("/api/v1/affiliate/{$this->slug}/click"),
            'comparison_data' => $this->comparison_data,
            'position' => $this->whenPivotLoaded('article_affiliate', fn () => $this->pivot->position),
        ];
    }
}
