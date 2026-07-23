<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_action_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seo_report_id')->nullable()->constrained()->nullOnDelete();

            // create_page | add_section | optimize_meta
            $table->string('action_type');
            // pending | published | dismissed
            $table->string('status')->default('pending')->index();
            // high | medium | low
            $table->string('priority')->default('medium');

            $table->string('title');
            $table->text('problem');
            $table->json('proposed');

            // Doelpagina bij add_section / optimize_meta.
            $table->foreignId('page_id')->nullable()->constrained('pages')->nullOnDelete();
            // Keyword dat dit item adresseert — wordt bij publicatie mee opgevolgd.
            $table->string('source_keyword')->nullable();
            // Display-info (volume, AI-Overview-vlag, …).
            $table->json('metric')->nullable();

            // Dedup-sleutel zodat hetzelfde advies niet elke week terugkomt.
            $table->string('fingerprint')->index();

            // Resultaat na publicatie.
            $table->foreignId('created_page_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->string('result_url')->nullable();

            $table->timestamp('applied_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_action_items');
    }
};
