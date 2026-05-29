<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_placements', function (Blueprint $table) {
            $table->unsignedInteger('reserved_width')->nullable()->after('ad_format');
            $table->unsignedInteger('reserved_height')->nullable()->after('reserved_width');
            $table->string('revenue_channel')->default('adsense')->after('reserved_height');
        });
    }

    public function down(): void
    {
        Schema::table('ad_placements', function (Blueprint $table) {
            $table->dropColumn(['reserved_width', 'reserved_height', 'revenue_channel']);
        });
    }
};
