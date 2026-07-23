<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_reports', function (Blueprint $table) {
            $table->id();
            $table->date('captured_at');
            $table->string('period')->default('weekly'); // weekly, manual
            $table->json('metrics')->nullable();   // numerieke samenvatting (delta's, top movers)
            $table->longText('advice')->nullable(); // AI-gegenereerde actielijst (markdown)
            $table->boolean('emailed')->default(false);
            $table->timestamps();

            $table->index('captured_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_reports');
    }
};
