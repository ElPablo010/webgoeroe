<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Generieke key/value-store voor site-instellingen (header, footer, …).
        // Eén rij per logische groep; `value` houdt een JSON-blob met alle velden
        // van die groep. Bewust generiek zodat latere instellingen-pagina's
        // (footer, social, openingsuren) dezelfde tabel hergebruiken.
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
