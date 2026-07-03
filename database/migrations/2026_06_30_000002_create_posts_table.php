<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('cover_url')->nullable();
            $table->string('cover_alt')->nullable();
            $table->json('tags')->nullable();
            // Auteur — zit in de post zodat we later meerdere auteurs kunnen ondersteunen
            $table->string('author_name')->default('De Webgoeroe');
            $table->text('author_bio')->nullable();
            $table->string('author_avatar_url')->nullable();
            $table->boolean('published')->default(false);
            $table->boolean('featured')->default(false);
            $table->timestamp('published_at')->nullable();
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
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
