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
 * Genereert de verbeteracties buiten de web-request. De AI-call schrijft
 * volledige landingspagina's uit en duurt daardoor ruim een minuut — te lang
 * voor een synchrone request op shared hosting, waar de knop dan in een
 * time-out loopt zonder dat er iets wordt opgeslagen.
 */
class GenerateSeoActionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 600;

    public function handle(SeoAdvisorService $advisor): void
    {
        $actions = $advisor->generateActions($advisor->buildContext());
        $stored = $advisor->storeActions($actions);

        Log::info('SEO-acties gegenereerd via de knop', $stored);
    }
}
