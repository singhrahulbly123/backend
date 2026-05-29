<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_usages', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 64);
            $table->string('operation')->nullable();
            $table->unsignedBigInteger('tokens')->default(0);
            $table->decimal('cost_inr', 12, 4)->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_usages');
    }
};
