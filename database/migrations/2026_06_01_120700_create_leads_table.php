<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->string('lead_type', 32);
            $table->nullableMorphs('source');
            $table->decimal('value', 10, 2)->nullable();
            $table->string('channel', 32)->nullable();
            $table->string('referrer_host')->nullable();
            $table->string('landing_path', 500)->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('locale', 5)->nullable();
            $table->timestamps();

            $table->index(['lead_type', 'created_at']);
            $table->index(['channel', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
