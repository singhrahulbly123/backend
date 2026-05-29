<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_skill_guides')) {
            return;
        }

        Schema::create('ai_skill_guides', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('category')->index();
            $table->string('career_stage')->default('beginner')->index();
            $table->text('summary')->nullable();
            $table->longText('body')->nullable();
            $table->json('skills')->nullable();
            $table->json('tools')->nullable();
            $table->json('projects')->nullable();
            $table->json('roadmap')->nullable();
            $table->json('faqs')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->string('status')->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_skill_guides');
    }
};
