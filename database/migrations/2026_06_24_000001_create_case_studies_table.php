<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_studies', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('client')->nullable();
            $table->string('industry')->nullable();
            // Media-velden bewaren altijd de URL-string (geen FK) zodat content
            // portabel blijft; dimensies worden via WebsiteMedia::dimensionsForUrl()
            // uit de media-tabel afgeleid.
            $table->string('cover_url')->nullable();
            $table->string('cover_alt')->nullable();
            $table->json('tags')->nullable();
            $table->text('excerpt')->nullable();
            $table->boolean('published')->default(false);
            $table->boolean('featured')->default(false);
            // SEO
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_robots')->nullable();
            $table->string('canonical_url')->nullable();
            $table->boolean('is_cornerstone')->default(false);
            $table->string('seo_image_url')->nullable();
            $table->string('seo_image_alt')->nullable();
            $table->timestamps();

            $table->index('published');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_studies');
    }
};
