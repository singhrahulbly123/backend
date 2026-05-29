<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_tools')) {
            Schema::create('ai_tools', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('category')->index();
                $table->string('tagline')->nullable();
                $table->text('description')->nullable();
                $table->string('website_url')->nullable();
                $table->string('affiliate_url')->nullable();
                $table->string('pricing')->nullable();
                $table->json('best_for')->nullable();
                $table->json('pros')->nullable();
                $table->json('cons')->nullable();
                $table->json('alternatives')->nullable();
                $table->decimal('rating', 3, 1)->default(4.5);
                $table->boolean('is_featured')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                $table->index(['is_active', 'is_featured', 'sort_order']);
            });
        }

        if (! Schema::hasTable('prompt_templates')) {
            Schema::create('prompt_templates', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('slug')->unique();
                $table->string('category')->index();
                $table->string('audience')->nullable()->index();
                $table->string('language', 20)->default('english');
                $table->text('use_case')->nullable();
                $table->longText('prompt');
                $table->json('tags')->nullable();
                $table->boolean('is_featured')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('copy_count')->default(0);
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                $table->index(['is_active', 'is_featured', 'copy_count']);
            });
        }

        if (! Schema::hasTable('daily_ai_briefs')) {
            Schema::create('daily_ai_briefs', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('slug')->unique();
                $table->text('summary')->nullable();
                $table->json('key_updates')->nullable();
                $table->json('tool_of_day')->nullable();
                $table->json('prompts')->nullable();
                $table->text('impact_india')->nullable();
                $table->string('cta_label')->nullable();
                $table->string('cta_url')->nullable();
                $table->string('status')->default('draft')->index();
                $table->timestamp('published_at')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_ai_briefs');
        Schema::dropIfExists('prompt_templates');
        Schema::dropIfExists('ai_tools');
    }
};
