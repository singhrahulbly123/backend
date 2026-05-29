<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->unsignedTinyInteger('india_impact_score')->default(70)->after('quality_score');
            $table->text('india_impact_summary')->nullable()->after('india_impact_score');
            $table->unsignedTinyInteger('ai_opportunity_score')->default(70)->after('india_impact_summary');
            $table->text('ai_opportunity_summary')->nullable()->after('ai_opportunity_score');
            $table->json('audience_roles')->nullable()->after('ai_opportunity_summary');
        });

        Schema::table('ai_tools', function (Blueprint $table) {
            $table->unsignedTinyInteger('trust_score')->default(70)->after('rating');
            $table->json('trust_breakdown')->nullable()->after('trust_score');
            $table->unsignedTinyInteger('opportunity_score')->default(70)->after('trust_breakdown');
            $table->text('opportunity_summary')->nullable()->after('opportunity_score');
            $table->json('audience_roles')->nullable()->after('opportunity_summary');
        });

        Schema::table('daily_ai_briefs', function (Blueprint $table) {
            $table->longText('voice_script')->nullable()->after('impact_india');
            $table->string('voice_audio_url')->nullable()->after('voice_script');
            $table->unsignedInteger('voice_duration_seconds')->nullable()->after('voice_audio_url');
        });
    }

    public function down(): void
    {
        Schema::table('daily_ai_briefs', function (Blueprint $table) {
            $table->dropColumn(['voice_script', 'voice_audio_url', 'voice_duration_seconds']);
        });

        Schema::table('ai_tools', function (Blueprint $table) {
            $table->dropColumn(['trust_score', 'trust_breakdown', 'opportunity_score', 'opportunity_summary', 'audience_roles']);
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['india_impact_score', 'india_impact_summary', 'ai_opportunity_score', 'ai_opportunity_summary', 'audience_roles']);
        });
    }
};
