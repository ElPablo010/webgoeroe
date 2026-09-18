<?php

namespace App\Console\Commands;

use App\Mail\SeoWeeklyReport;
use App\Models\SeoReport;
use App\Models\Setting;
use App\Services\DataForSeoService;
use App\Services\Seo\ActionBacklog;
use App\Services\SeoAdvisorService;
use App\Services\SeoCollector;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

class SeoWeeklyReportCommand extends Command
{
    protected $signature = 'seo:weekly-report {--no-mail : Verzamel en genereer advies, maar verstuur geen e-mail}';

    protected $description = 'Verzamelt SEO-data, genereert AI-advies en mailt de wekelijkse stand van zaken';

    public function handle(DataForSeoService $api, SeoCollector $collector, SeoAdvisorService $advisor, ActionBacklog $backlog): int
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
        //     Staat de lijst nog vol, dan slaan we dit deel over — de briefing
        //     hierboven gaat wél gewoon door. Die blijft waardevol in een week
        //     zonder nieuwe voorstellen, en hem meesmoren zou het stil maken
        //     precies wanneer er iets uit te leggen valt.
        $expired = $backlog->expireStale();
        if ($expired > 0) {
            $this->info("{$expired} achterhaalde voorstellen vervallen.");
        }

        $stored = null;
        if ($backlog->isBlocked()) {
            $this->warn("Geen nieuwe verbeteracties: er staan er nog {$backlog->openCount()} open (grens: {$backlog->limit()}).");
        } else {
            // Dedup op fingerprint binnen een venster — zie storeActions().
            $stored = $advisor->storeActions($advisor->generateActions($context), $report->id, $backlog->room());
            $this->info("{$stored['created']} nieuwe verbeteracties aangemaakt ({$stored['proposed']} voorgesteld).");
            if ($stored['duplicates'] > 0) {
                $this->warn("{$stored['duplicates']} voorstellen overgeslagen: die stonden er al.");
            }
            if ($stored['skipped'] > 0) {
                $this->warn("{$stored['skipped']} voorstellen vielen weg: de lijst zat aan haar grens.");
            }
        }

        // 5. Mail de stand van zaken.
        if (!$this->option('no-mail')) {
            $recipient = Setting::get('seo_report_email') ?: config('mail.from.address');
            if ($recipient) {
                Mail::to($recipient)->send(new SeoWeeklyReport(
                    context: $context,
                    advice: $advice,
                    dashboardUrl: url('/admin/seo-actions'),
                    backlog: $this->backlogSummary($backlog, $stored, $expired),
                ));
                $report->update(['emailed' => true]);
                $this->info("Rapport gemaild naar {$recipient}.");
            } else {
                $this->warn('Geen ontvanger ingesteld (seo_report_email / MAIL_FROM_ADDRESS).');
            }
        }

        return self::SUCCESS;
    }

    /**
     * Wat de mail over de actielijst moet vertellen. Bij een geblokkeerde week
     * is een kaal aantal te weinig: dan lees je zeven weken lang "niets
     * nieuws" en haak je af. Daarom gaan de openstaande items zelf mee, met
     * hun ouderdom en de datum waarop de oudste vervalt — dat maakt van de
     * melding een beslissing in plaats van een mededeling.
     *
     * @param  array{proposed:int,created:int,duplicates:int,skipped:int}|null  $stored
     * @return array<string,mixed>
     */
    protected function backlogSummary(ActionBacklog $backlog, ?array $stored, int $expired): array
    {
        $blocked = $stored === null;

        return [
            'blocked' => $blocked,
            'created' => $stored['created'] ?? 0,
            'expired' => $expired,
            'open' => $backlog->openCount(),
            'limit' => $backlog->limit(),
            'next_expiry' => $blocked ? $backlog->nextExpiryAt() : null,
            'items' => $blocked
                ? $backlog->openItems()->map(fn ($i) => [
                    'title' => $i->title,
                    'days' => (int) $i->created_at->startOfDay()->diffInDays(Carbon::today()),
                ])->all()
                : [],
        ];
    }
}
