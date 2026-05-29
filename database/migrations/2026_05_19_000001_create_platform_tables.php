<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('locale', 10)->default('en');
            $table->text('description')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreign('parent_id')->references('id')->on('categories')->nullOnDelete();
            $table->index(['locale', 'is_active']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('journalist')->after('email');
            $table->string('avatar')->nullable()->after('role');
            $table->string('bio', 500)->nullable()->after('avatar');
            $table->string('designation')->nullable()->after('bio');
            $table->boolean('is_verified_author')->default(false)->after('designation');
            $table->json('expertise_topics')->nullable()->after('is_verified_author');
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
        });

        Schema::create('author_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('display_name');
            $table->string('slug')->unique();
            $table->text('bio')->nullable();
            $table->string('twitter')->nullable();
            $table->string('linkedin')->nullable();
            $table->json('credentials')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('editor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('locale', 10)->default('en');
            $table->string('content_type')->default('news');
            $table->text('excerpt')->nullable();
            $table->text('ai_summary')->nullable();
            $table->json('key_points')->nullable();
            $table->longText('body');
            $table->string('featured_image')->nullable();
            $table->json('gallery')->nullable();
            $table->string('status')->default('draft');
            $table->boolean('is_breaking')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_ai_generated')->default(false);
            $table->boolean('human_reviewed')->default(false);
            $table->boolean('fact_checked')->default(false);
            $table->json('sources')->nullable();
            $table->json('timeline')->nullable();
            $table->json('faqs')->nullable();
            $table->decimal('readability_score', 5, 2)->nullable();
            $table->decimal('seo_score', 5, 2)->nullable();
            $table->decimal('quality_score', 5, 2)->nullable();
            $table->unsignedBigInteger('views_count')->default(0);
            $table->unsignedInteger('reading_time_minutes')->default(3);
            $table->string('canonical_url')->nullable();
            $table->foreignId('translation_of')->nullable()->constrained('articles')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['status', 'published_at']);
            $table->index(['locale', 'is_breaking']);
            $table->index('content_type');
        });

        Schema::create('article_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->longText('body');
            $table->json('metadata')->nullable();
            $table->string('revision_note')->nullable();
            $table->timestamps();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('locale', 10)->default('en');
            $table->timestamps();
        });

        Schema::create('article_tag', function (Blueprint $table) {
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['article_id', 'tag_id']);
        });

        Schema::create('seo_meta', function (Blueprint $table) {
            $table->id();
            $table->morphs('seoable');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('og_title')->nullable();
            $table->text('og_description')->nullable();
            $table->string('og_image')->nullable();
            $table->json('keywords')->nullable();
            $table->json('schema_markup')->nullable();
            $table->string('canonical_url')->nullable();
            $table->json('hreflang')->nullable();
            $table->boolean('discover_optimized')->default(false);
            $table->boolean('noindex')->default(false);
            $table->timestamps();
        });

        Schema::create('ai_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->string('operation');
            $table->nullableMorphs('loggable');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->unsignedInteger('tokens_used')->default(0);
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('status')->default('success');
            $table->text('error_message')->nullable();
            $table->timestamps();
            $table->index(['provider', 'operation']);
        });

        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('filename');
            $table->string('mime_type');
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt_text')->nullable();
            $table->json('variants')->nullable();
            $table->timestamps();
        });

        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type');
            $table->nullableMorphs('trackable');
            $table->string('session_id')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('referrer')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->json('metadata')->nullable();
            $table->decimal('revenue_inr', 12, 4)->nullable();
            $table->timestamp('recorded_at');
            $table->index(['event_type', 'recorded_at']);
        });

        Schema::create('web_stories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('locale', 10)->default('en');
            $table->string('cover_image');
            $table->json('pages');
            $table->string('status')->default('draft');
            $table->boolean('is_ai_generated')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('affiliate_links', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('category');
            $table->text('description')->nullable();
            $table->string('destination_url');
            $table->string('tracking_code')->unique();
            $table->decimal('commission_rate', 5, 2)->nullable();
            $table->string('network')->nullable();
            $table->json('comparison_data')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('clicks_count')->default(0);
            $table->unsignedBigInteger('conversions_count')->default(0);
            $table->timestamps();
        });

        Schema::create('article_affiliate', function (Blueprint $table) {
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('affiliate_link_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->primary(['article_id', 'affiliate_link_id']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('endpoint')->unique();
            $table->string('public_key');
            $table->string('auth_token');
            $table->string('locale', 10)->default('en');
            $table->json('topics')->nullable();
            $table->timestamps();
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->string('author_name')->nullable();
            $table->string('author_email')->nullable();
            $table->text('body');
            $table->string('status')->default('pending');
            $table->unsignedInteger('likes_count')->default(0);
            $table->timestamps();
        });

        Schema::create('trending_topics', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('source');
            $table->string('locale', 10)->default('en');
            $table->decimal('trend_score', 8, 2)->default(0);
            $table->decimal('seo_value', 8, 2)->nullable();
            $table->decimal('virality_score', 8, 2)->nullable();
            $table->decimal('rpm_potential', 8, 2)->nullable();
            $table->json('metadata')->nullable();
            $table->string('status')->default('detected');
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('detected_at');
            $table->timestamps();
            $table->index(['status', 'trend_score']);
        });

        Schema::create('live_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('headline');
            $table->text('content');
            $table->boolean('is_breaking')->default(false);
            $table->timestamp('published_at');
            $table->timestamps();
        });

        Schema::create('viral_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->string('platform');
            $table->string('content_type');
            $table->text('script')->nullable();
            $table->json('assets')->nullable();
            $table->string('status')->default('draft');
            $table->string('external_url')->nullable();
            $table->timestamps();
        });

        Schema::create('reading_history', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('progress_percent')->default(0);
            $table->timestamp('read_at');
            $table->unique(['session_id', 'article_id']);
        });

        Schema::create('ad_placements', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slot_key')->unique();
            $table->string('page_type');
            $table->string('ad_format');
            $table->text('ad_code')->nullable();
            $table->boolean('lazy_load')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(0);
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->nullableMorphs('auditable');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index('action');
        });

        Schema::create('content_quality_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->decimal('plagiarism_score', 5, 2)->nullable();
            $table->decimal('hallucination_risk', 5, 2)->nullable();
            $table->decimal('spam_score', 5, 2)->nullable();
            $table->decimal('readability_score', 5, 2)->nullable();
            $table->decimal('seo_score', 5, 2)->nullable();
            $table->json('fact_checks')->nullable();
            $table->json('recommendations')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_quality_reports');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('ad_placements');
        Schema::dropIfExists('reading_history');
        Schema::dropIfExists('viral_contents');
        Schema::dropIfExists('live_updates');
        Schema::dropIfExists('trending_topics');
        Schema::dropIfExists('comments');
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('article_affiliate');
        Schema::dropIfExists('affiliate_links');
        Schema::dropIfExists('web_stories');
        Schema::dropIfExists('analytics_events');
        Schema::dropIfExists('media');
        Schema::dropIfExists('ai_logs');
        Schema::dropIfExists('seo_meta');
        Schema::dropIfExists('article_tag');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('article_revisions');
        Schema::dropIfExists('articles');
        Schema::dropIfExists('author_profiles');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'role', 'avatar', 'bio', 'designation',
                'is_verified_author', 'expertise_topics', 'last_login_at',
            ]);
        });
        Schema::dropIfExists('categories');
    }
};
