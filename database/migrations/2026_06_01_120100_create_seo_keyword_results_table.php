<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_keyword_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_keyword_id')->constrained('seo_keywords')->cascadeOnDelete();
            $table->date('checked_at');
            $table->unsignedSmallInteger('rank_absolute')->nullable(); // positie in volledige SERP (incl. features)
            $table->unsignedSmallInteger('rank_group')->nullable();     // positie binnen organische resultaten
            $table->unsignedSmallInteger('previous_rank')->nullable();  // vorige meting, voor delta
            $table->string('url', 1024)->nullable();                    // welke pagina rankt
            $table->unsignedInteger('search_volume')->nullable();
            $table->json('serp_features')->nullable();                  // welke SERP-features aanwezig (featured snippet, ...)
            $table->boolean('in_ai_overview')->default(false);          // verschijnt er een Google AI Overview
            $table->boolean('ai_overview_cited')->default(false);       // wordt ons domein erin geciteerd (GEO)
            $table->timestamps();

            $table->index(['seo_keyword_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_keyword_results');
    }
};
