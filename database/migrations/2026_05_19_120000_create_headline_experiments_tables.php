<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('headline_experiments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('locale', 10)->default('en');
            $table->string('status', 32)->default('draft');
            $table->foreignId('winner_variant_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('headline_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('headline_experiment_id')->constrained('headline_experiments')->cascadeOnDelete();
            $table->text('headline');
            $table->unsignedSmallInteger('score')->default(0);
            $table->text('reason')->nullable();
            $table->unsignedInteger('impressions_count')->default(0);
            $table->unsignedInteger('clicks_count')->default(0);
            $table->decimal('ctr', 5, 2)->default(0);
            $table->timestamps();
        });

        Schema::table('headline_experiments', function (Blueprint $table) {
            $table->foreign('winner_variant_id')->references('id')->on('headline_variants')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('headline_variants');
        Schema::dropIfExists('headline_experiments');
    }
};
