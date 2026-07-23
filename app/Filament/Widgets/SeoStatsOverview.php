<?php

namespace App\Filament\Widgets;

use App\Support\SeoStats;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * De vier kerncijfers bovenaan het SEO-dashboard.
 *
 * Elke stat toont waar mogelijk de beweging t.o.v. de vorige meting; zonder
 * tweede snapshot blijft de beschrijving leeg i.p.v. een pijl van 0 te tonen.
 */
class SeoStatsOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Kerncijfers';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $latest = SeoStats::latest();
        $previous = SeoStats::previous();
        $keywords = SeoStats::keywordStats();

        return [
            $this->trendStat(
                label: 'Geschat organisch verkeer',
                current: $latest?->organic_etv,
                earlier: $previous?->organic_etv,
                icon: 'heroicon-o-arrow-trending-up',
                help: 'Bezoekers per maand die Google verwacht op basis van je posities.',
            ),
            $this->trendStat(
                label: 'Keywords in Google',
                current: $latest?->organic_keywords_count,
                earlier: $previous?->organic_keywords_count,
                icon: 'heroicon-o-magnifying-glass',
                help: 'Alle zoekwoorden waarop je site rankt — niet je opgevolgde lijst.',
            ),
            $this->trendStat(
                label: 'In de top 10',
                current: $latest?->top10,
                earlier: $previous?->top10,
                icon: 'heroicon-o-trophy',
                help: 'Posities 1 t/m 10, waar vrijwel alle kliks gebeuren.',
            ),
            Stat::make('Opgevolgde keywords', (string) $keywords['total'])
                ->description($this->trackedDescription($keywords))
                ->descriptionIcon('heroicon-o-eye')
                ->color($keywords['total'] === 0 ? 'warning' : 'primary'),
        ];
    }

    /**
     * Bouw een stat met een pijl en kleur op basis van de beweging.
     * Meer is hier altijd beter (verkeer, keywords, top-10-posities).
     */
    protected function trendStat(
        string $label,
        ?int $current,
        ?int $earlier,
        string $icon,
        string $help,
    ): Stat {
        $stat = Stat::make($label, $current !== null ? number_format($current, 0, ',', '.') : '—')
            ->descriptionIcon($icon)
            ->extraAttributes(['title' => $help]);

        if ($current === null || $earlier === null) {
            return $stat->description($help)->color('gray');
        }

        $delta = $current - $earlier;

        if ($delta === 0) {
            return $stat->description('Ongewijzigd')->color('gray');
        }

        return $stat
            ->description(($delta > 0 ? '+' : '').number_format($delta, 0, ',', '.').' sinds vorige meting')
            ->descriptionIcon($delta > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
            ->color($delta > 0 ? 'success' : 'danger');
    }

    /**
     * @param  array<string, int|float|null>  $keywords
     */
    protected function trackedDescription(array $keywords): string
    {
        if ($keywords['total'] === 0) {
            return 'Voeg kernzinnen toe om posities te volgen';
        }

        if ($keywords['tracked'] === 0) {
            return 'Nog niet gemeten — ververs de cijfers';
        }

        return "{$keywords['top10']} in de top 10, {$keywords['top3']} in de top 3";
    }
}
