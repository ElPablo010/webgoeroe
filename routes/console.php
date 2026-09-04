<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Verwerk queue-jobs elke minuut: een worker die de queue leegwerkt en dan
// stopt (--stop-when-empty). Geen permanente daemon nodig — Combell-vriendelijk,
// zolang de server-cron `schedule:run` elke minuut draait.
Schedule::command('queue:work --stop-when-empty --queue=default --tries=3')
    ->everyMinute()
    ->withoutOverlapping();

// Wekelijkse SEO-briefing: verzamelt data, genereert advies + acties en mailt
// de stand van zaken. Draait via de queue-worker hierboven.
Schedule::command('seo:weekly-report')
    ->weeklyOn(1, '7:00') // maandag 7:00
    ->withoutOverlapping();

// Google Search Console-cijfers ophalen (dagelijks 6:00, Groei-meetlaag).
// Google levert met ~3 dagen vertraging en herziet recente dagen nog; de sync
// haalt telkens het laatste venster opnieuw op en overschrijft per dag.
Schedule::command('seo:sync-search-console')
    ->dailyAt('6:00')
    ->withoutOverlapping();
