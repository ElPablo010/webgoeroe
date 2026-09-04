<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gsc_dimension_metrics', function (Blueprint $table) {
            $table->id();
            // Zie gsc_daily_metrics: 191 houdt de samengestelde unique-index
            // onder de key-lengtelimiet van oudere MySQL-versies.
            $table->string('site_url', 191);
            $table->date('period_start');
            $table->date('period_end');

            // 'query' (zoekterm) of 'page' (URL)
            $table->string('dimension', 16);
            $table->text('value');
            // URL's zijn te lang voor een MySQL-index; de hash draagt de uniciteit.
            $table->char('value_hash', 32);

            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('impressions')->default(0);
            $table->decimal('ctr', 8, 5)->default(0);
            $table->decimal('position', 8, 3)->default(0);

            $table->timestamps();

            $table->unique(
                ['site_url', 'period_start', 'period_end', 'dimension', 'value_hash'],
                'gsc_dim_unique'
            );
            $table->index(['site_url', 'dimension', 'period_end'], 'gsc_dim_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gsc_dimension_metrics');
    }
};
