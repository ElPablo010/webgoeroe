<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gsc_daily_metrics', function (Blueprint $table) {
            $table->id();
            // 191 i.p.v. 255: houdt de samengestelde index ruim onder de
            // key-lengtelimiet van oudere MySQL-versies (utf8mb4 = 4 bytes/teken).
            $table->string('site_url', 191); // bv. "sc-domain:bailandolatino.be"
            $table->date('date');

            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('ctr', 8, 5)->default(0);      // 0..1
            $table->decimal('position', 8, 3)->default(0); // gemiddelde positie

            $table->timestamps();

            // Search Console herziet cijfers van de voorbije dagen. We halen
            // daarom telkens een venster opnieuw op en overschrijven per dag.
            $table->unique(['site_url', 'date']);
            $table->index(['site_url', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gsc_daily_metrics');
    }
};
