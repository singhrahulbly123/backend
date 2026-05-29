<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_tools', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_tools', 'use_cases')) {
                $table->json('use_cases')->nullable()->after('alternatives');
            }
            if (! Schema::hasColumn('ai_tools', 'faqs')) {
                $table->json('faqs')->nullable()->after('use_cases');
            }
            if (! Schema::hasColumn('ai_tools', 'seo_title')) {
                $table->string('seo_title')->nullable()->after('faqs');
            }
            if (! Schema::hasColumn('ai_tools', 'seo_description')) {
                $table->text('seo_description')->nullable()->after('seo_title');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_tools', function (Blueprint $table) {
            foreach (['use_cases', 'faqs', 'seo_title', 'seo_description'] as $column) {
                if (Schema::hasColumn('ai_tools', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
