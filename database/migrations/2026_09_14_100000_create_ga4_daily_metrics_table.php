<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ga4_daily_metrics', function (Blueprint $table) {
            $table->id();
            // Het numerieke property-ID als string; niet te verwarren met het
            // G-XXXX meet-ID uit de meetcode op de site.
            $table->string('property_id', 64);
            $table->date('date');

            $table->unsignedInteger('sessions')->default(0);
            $table->unsignedInteger('active_users')->default(0);
            $table->unsignedInteger('page_views')->default(0);
            $table->unsignedInteger('engaged_sessions')->default(0);
            // Gemiddelde sessieduur in seconden, zoals Google ze teruggeeft.
            $table->decimal('avg_session_seconds', 10, 2)->default(0);

            $table->timestamps();

            $table->unique(['property_id', 'date'], 'ga4_daily_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ga4_daily_metrics');
    }
};
