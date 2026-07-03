<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('location')->unique(); // main, footer_1, footer_2, footer_3
            $table->string('name');                // admin-label
            $table->string('title')->nullable();   // publieksgerichte kop (footerkolom)
            $table->timestamps();
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->string('label');
            $table->foreignId('page_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->string('url')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('target_blank')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
    }
};
