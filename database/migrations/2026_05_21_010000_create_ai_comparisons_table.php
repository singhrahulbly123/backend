<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_comparisons')) {
            return;
        }

        Schema::create('ai_comparisons', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('category')->index();
            $table->string('tool_a');
            $table->string('tool_b');
            $table->text('summary')->nullable();
            $table->string('winner')->nullable();
            $table->json('best_for')->nullable();
            $table->json('scorecard')->nullable();
            $table->json('pros_cons')->nullable();
            $table->json('faqs')->nullable();
            $table->string('cta_label')->nullable();
            $table->string('cta_url')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->string('status')->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_comparisons');
    }
};
