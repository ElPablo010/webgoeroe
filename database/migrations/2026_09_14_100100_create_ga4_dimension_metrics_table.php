<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ga4_dimension_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('property_id', 64);
            $table->date('period_start');
            $table->date('period_end');

            // 'page' (pagina-pad) of 'channel' (kanaalgroep)
            $table->string('dimension', 16);
            $table->text('value');
            // Paden zijn te lang voor een MySQL-index; de hash draagt de uniciteit.
            $table->char('value_hash', 32);

            $table->unsignedInteger('sessions')->default(0);
            $table->unsignedInteger('page_views')->default(0);
            $table->unsignedInteger('engaged_sessions')->default(0);
            $table->decimal('avg_session_seconds', 10, 2)->default(0);

            $table->timestamps();

            $table->unique(
                ['property_id', 'period_start', 'period_end', 'dimension', 'value_hash'],
                'ga4_dim_unique'
            );
            $table->index(['property_id', 'dimension', 'period_end'], 'ga4_dim_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ga4_dimension_metrics');
    }
};
