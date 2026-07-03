<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_sections', function (Blueprint $table) {
            $table->id();
            $table->string('sectionable_type');
            $table->unsignedBigInteger('sectionable_id');
            $table->string('section_type');
            $table->unsignedInteger('position')->default(0);
            $table->json('content')->nullable();
            $table->string('locale', 8)->nullable();
            $table->foreignId('translation_of')->nullable()->constrained('page_sections')->nullOnDelete();
            $table->timestamps();

            $table->index(['sectionable_type', 'sectionable_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_sections');
    }
};
