<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug');
            $table->string('locale', 8)->default('nl');
            $table->foreignId('translation_of')->nullable()->constrained('pages')->nullOnDelete();
            $table->boolean('is_homepage')->default(false);
            $table->boolean('published')->default(false);
            // SEO
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_robots')->nullable();
            $table->string('canonical_url')->nullable();
            $table->boolean('is_cornerstone')->default(false);
            // Media-velden bewaren altijd de URL-string (geen FK) zodat content
            // portabel blijft; dimensies worden via WebsiteMedia::dimensionsForUrl()
            // uit de media-tabel afgeleid.
            $table->string('seo_image_url')->nullable();
            $table->string('seo_image_alt')->nullable();
            $table->timestamps();

            $table->unique(['locale', 'slug']);
            $table->index('is_homepage');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
