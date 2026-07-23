<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_geo_checks', function (Blueprint $table) {
            $table->id();
            $table->string('prompt', 1024);          // de vraag die aan de AI-engine gesteld wordt
            $table->string('engine')->default('ai_overview'); // ai_overview, chatgpt, perplexity, gemini
            $table->date('checked_at');
            $table->boolean('brand_mentioned')->default(false); // wordt de merknaam vermeld (Setting brand_name)
            $table->boolean('domain_cited')->default(false);    // wordt het eigen domein gelinkt
            $table->unsignedSmallInteger('mention_rank')->nullable(); // hoeveelste vermelde bron
            $table->text('response_excerpt')->nullable();       // relevant fragment uit het AI-antwoord
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->index(['engine', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_geo_checks');
    }
};
