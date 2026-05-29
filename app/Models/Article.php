<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\LiveUpdate;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Article extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'author_id', 'editor_id', 'category_id', 'title', 'slug', 'locale',
        'content_type', 'excerpt', 'ai_summary', 'key_points', 'body', 'featured_image',
        'gallery', 'status', 'is_breaking', 'is_featured', 'is_ai_generated',
        'human_reviewed', 'fact_checked', 'sources', 'timeline', 'faqs',
        'readability_score', 'seo_score', 'quality_score', 'india_impact_score',
        'india_impact_summary', 'ai_opportunity_score', 'ai_opportunity_summary',
        'audience_roles', 'views_count',
        'reading_time_minutes', 'canonical_url', 'translation_of', 'published_at', 'scheduled_at',
    ];

    protected function casts(): array
    {
        return [
            'key_points' => 'array',
            'gallery' => 'array',
            'sources' => 'array',
            'timeline' => 'array',
            'faqs' => 'array',
            'audience_roles' => 'array',
            'india_impact_score' => 'integer',
            'ai_opportunity_score' => 'integer',
            'is_breaking' => 'boolean',
            'is_featured' => 'boolean',
            'is_ai_generated' => 'boolean',
            'human_reviewed' => 'boolean',
            'fact_checked' => 'boolean',
            'published_at' => 'datetime',
            'scheduled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Article $article) {
            $article->uuid ??= (string) Str::uuid();
            $article->slug ??= Str::slug($article->title).'-'.Str::random(6);
        });
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'editor_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function seoMeta(): MorphOne
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ArticleRevision::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function qualityReport(): HasOne
    {
        return $this->hasOne(ContentQualityReport::class);
    }

    public function liveUpdates(): HasMany
    {
        return $this->hasMany(LiveUpdate::class)->latest('published_at');
    }

    public function affiliateLinks(): BelongsToMany
    {
        return $this->belongsToMany(AffiliateLink::class, 'article_affiliate')->withPivot('position');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')->where('published_at', '<=', now());
    }
}
