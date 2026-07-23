<?php

namespace App\Console\Commands;

use App\Mail\SeoWeeklyReport;
use App\Models\SeoActionItem;
use App\Models\SeoReport;
use App\Models\Setting;
use App\Services\DataForSeoService;
use App\Services\SeoAdvisorService;
use App\Services\SeoCollector;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class SeoWeeklyReportCommand extends Command
{
    protected $signature = 'seo:weekly-report {--no-mail : Verzamel en genereer advies, maar verstuur geen e-mail}';

    protected $description = 'Verzamelt SEO-data, genereert AI-advies en mailt de wekelijkse stand van zaken';

    public function handle(DataForSeoService $api, SeoCollector $collector, SeoAdvisorService $advisor): int
    {
        if (!$api->isConfigured()) {
            $this->warn('DataForSEO is niet geconfigureerd — overgeslagen.');
            return self::SUCCESS;
        }

        // 1. Verzamel verse data (synchroon — dit command draait in CLI, geen queue nodig).
        $this->info('SEO-data verzamelen…');
        $result = $collector->collectAll();
        $this->info("Posities bijgewerkt voor {$result['keywords_tracked']} keywords. Kost: \${$result['spent']}");

        // 2. Optioneel: GEO-checks draaien als er prompts ingesteld zijn.
        if ($collector->geoPrompts()) {
            $geoCount = $collector->runGeoChecks('chat_gpt');
            $this->info("{$geoCount} GEO-checks uitgevoerd.");
        }

        // 3. Bouw context + AI-advies.
        $context = $advisor->buildContext();
        $advice = $advisor->generateAdvice($context);
        $this->info($advice ? 'AI-advies gegenereerd.' : 'Geen AI-advies (Anthropic-key ontbreekt of fout).');

        // 4. Bewaar het rapport.
        $report = SeoReport::create([
            'captured_at' => Carbon::today(),
            'period' => 'weekly',
            'metrics' => [
                'stats' => $context['stats'] ?? [],
                'up' => $context['up'] ?? [],
                'down' => $context['down'] ?? [],
                'opportunities' => $context['opportunities'] ?? [],
            ],
            'advice' => $advice,
            'emailed' => false,
        ]);

        // 4b. Gestructureerde verbeteracties voor het goedkeuringsdashboard.
        //     Dedup op fingerprint: een item dat al bestaat (pending, gepubliceerd
        //     of eerder genegeerd) komt niet opnieuw terug.
        $actions = $advisor->generateActions($context);
        $newActions = 0;
        foreach ($actions as $action) {
            if (SeoActionItem::where('fingerprint', $action['fingerprint'])->exists()) {
                continue;
            }
            SeoActionItem::create(array_merge($action, ['seo_report_id' => $report->id]));
            $newActions++;
        }
        $this->info("{$newActions} nieuwe verbeteracties aangemaakt (" . count($actions) . ' voorgesteld).');

        // 5. Mail de stand van zaken.
        if (!$this->option('no-mail')) {
            $recipient = Setting::get('seo_report_email') ?: config('mail.from.address');
            if ($recipient) {
                Mail::to($recipient)->send(new SeoWeeklyReport(
                    context: $context,
                    advice: $advice,
                    dashboardUrl: url('/admin/seo-actions'),
                ));
                $report->update(['emailed' => true]);
                $this->info("Rapport gemaild naar {$recipient}.");
            } else {
                $this->warn('Geen ontvanger ingesteld (seo_report_email / MAIL_FROM_ADDRESS).');
            }
        }

        return self::SUCCESS;
    }
}
