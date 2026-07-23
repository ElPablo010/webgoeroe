<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_site_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('target'); // domein
            $table->integer('location_code')->default(2056);
            $table->string('language_code', 8)->default('nl');
            $table->date('captured_at');

            // Organische zichtbaarheid (DataForSEO Labs)
            $table->unsignedInteger('organic_keywords_count')->nullable();
            $table->unsignedInteger('organic_etv')->nullable(); // geschat maandelijks organisch verkeer
            $table->unsignedSmallInteger('pos_1')->nullable();
            $table->unsignedSmallInteger('pos_2_3')->nullable();
            $table->unsignedSmallInteger('pos_4_10')->nullable();
            $table->unsignedSmallInteger('pos_11_20')->nullable();
            $table->unsignedSmallInteger('pos_21_100')->nullable();

            // Backlinks (DataForSEO Backlinks summary)
            $table->unsignedBigInteger('backlinks_count')->nullable();
            $table->unsignedInteger('referring_domains')->nullable();
            $table->unsignedSmallInteger('domain_rank')->nullable(); // 0-1000 DataForSEO rank

            // On-page gezondheid (laatst bekende score)
            $table->unsignedTinyInteger('onpage_score')->nullable(); // 0-100

            $table->json('raw')->nullable(); // volledige payload voor naslag
            $table->timestamps();

            $table->index(['target', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_site_snapshots');
    }
};
