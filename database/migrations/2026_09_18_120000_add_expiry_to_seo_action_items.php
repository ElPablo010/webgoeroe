<?php

use App\Services\Seo\ActionBacklog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Verbeteracties vervallen voortaan vanzelf (zie ActionBacklog).
 *
 * `reopened_at` bestaat omdat de vervaltermijn anders niet te ontsnappen is:
 * zet je een vervallen voorstel terug op de lijst, dan zou het bij de
 * eerstvolgende run meteen opnieuw vervallen — z'n `created_at` ligt immers al
 * ver achter ons. De vervalklok loopt daarom vanaf `reopened_at` zodra die
 * gezet is. `created_at` blijft onaangeroerd, zodat "laatst gegenereerd" op het
 * scherm blijft kloppen.
 *
 * En meteen schoon beginnen: alles wat nu nog onbeoordeeld openstaat vervalt.
 * Die voorstellen dateren van weken terug en zijn gebouwd op posities die
 * intussen verschoven zijn; ze meenemen zou de nieuwe grens onmiddellijk
 * dichtzetten zonder dat er één bruikbaar voorstel tussen zit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_action_items', function (Blueprint $table) {
            $table->timestamp('reopened_at')->nullable()->after('dismissed_at');
        });

        DB::table('seo_action_items')
            ->where('status', 'pending')
            ->update([
                'status' => ActionBacklog::STATUS_EXPIRED,
                'dismissed_at' => now(),
            ]);
    }

    public function down(): void
    {
        Schema::table('seo_action_items', function (Blueprint $table) {
            $table->dropColumn('reopened_at');
        });
    }
};
