<?php

namespace App\Jobs;

use App\Services\SeoAdvisorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Draait het keyword-onderzoek buiten de web-request: een AI-call plus twee
 * DataForSEO-calls samen duren te lang voor een synchrone request op shared
 * hosting (zelfde reden als GenerateSeoActionsJob).
 */
class SuggestKeywordsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 300;

    public function handle(SeoAdvisorService $advisor): void
    {
        $suggestions = $advisor->suggestKeywords();

        Log::info('Keyword-onderzoek afgerond', ['candidates' => count($suggestions)]);
    }
}
