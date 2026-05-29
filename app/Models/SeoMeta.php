<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SeoMeta extends Model
{
    protected $table = 'seo_meta';

    protected $fillable = [
        'meta_title', 'meta_description', 'og_title', 'og_description', 'og_image',
        'discover_thumbnail', 'keywords', 'schema_markup', 'canonical_url', 'hreflang', 'discover_optimized', 'noindex',
    ];

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'schema_markup' => 'array',
            'hreflang' => 'array',
            'discover_thumbnail' => 'string',
            'discover_optimized' => 'boolean',
            'noindex' => 'boolean',
        ];
    }

    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }
}
