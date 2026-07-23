<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_audit_runs', function (Blueprint $table) {
            $table->id();
            $table->string('target');
            $table->string('status')->default('pending'); // pending, crawling, completed, failed
            $table->string('task_id')->nullable();         // DataForSEO on-page task id
            $table->unsignedTinyInteger('onpage_score')->nullable(); // 0-100
            $table->unsignedInteger('pages_crawled')->nullable();
            $table->unsignedInteger('pages_with_issues')->nullable();
            $table->unsignedInteger('critical_count')->default(0);
            $table->unsignedInteger('warning_count')->default(0);
            $table->json('checks')->nullable();  // samenvatting per check-type (broken links, missing titles, ...)
            $table->json('top_issues')->nullable(); // concrete probleempagina's
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['target', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_audit_runs');
    }
};
