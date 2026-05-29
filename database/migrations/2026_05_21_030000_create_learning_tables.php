<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('learning_paths')) {
            Schema::create('learning_paths', function (Blueprint $table) {
                $table->id();
                $table->string('title');
                $table->string('slug')->unique();
                $table->string('category')->index();
                $table->string('level')->default('beginner')->index();
                $table->text('description')->nullable();
                $table->json('outcomes')->nullable();
                $table->json('audience')->nullable();
                $table->unsignedInteger('duration_minutes')->default(30);
                $table->boolean('is_featured')->default(false);
                $table->string('status')->default('draft')->index();
                $table->timestamp('published_at')->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('learning_lessons')) {
            Schema::create('learning_lessons', function (Blueprint $table) {
                $table->id();
                $table->foreignId('learning_path_id')->constrained()->cascadeOnDelete();
                $table->string('title');
                $table->string('slug')->unique();
                $table->text('summary')->nullable();
                $table->longText('content');
                $table->json('action_steps')->nullable();
                $table->json('resources')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->unsignedInteger('duration_minutes')->default(5);
                $table->boolean('is_free')->default(true);
                $table->string('status')->default('published')->index();
                $table->timestamp('published_at')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_lessons');
        Schema::dropIfExists('learning_paths');
    }
};
