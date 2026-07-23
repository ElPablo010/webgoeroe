<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_keywords', function (Blueprint $table) {
            $table->id();
            $table->string('keyword');
            $table->integer('location_code')->default(2056); // België
            $table->string('language_code', 8)->default('nl');
            $table->string('tag')->nullable(); // optionele groepering (bv. per thema of dienst)
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['keyword', 'location_code', 'language_code']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_keywords');
    }
};
