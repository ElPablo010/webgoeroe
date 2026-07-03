<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index();         // 'contact', 'quote', …
            $table->json('data');                     // de ingezonden velden
            $table->string('page_url')->nullable();   // waar het formulier stond
            $table->string('ip', 45)->nullable();
            $table->timestamp('read_at')->nullable(); // null = nog niet gelezen
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
    }
};
