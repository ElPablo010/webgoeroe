<?php

namespace App\Filament\Widgets;

use App\Services\Ga4Collector;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * Gemeten gedrag óp de site van de laatste 28 dagen, vergeleken met de 28
 * dagen ervoor. De tegenhanger van GscStatsOverview: die vertelt hoe mensen
 * binnenkwamen, deze wat ze daarna deden.
 *
 * Enkel op de Verkeer-pagina — niet op het algemene dashboard.
 */
class Ga4StatsOverview extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Op de site';

    protected ?string $description = 'Telt enkel bezoekers die analytische cookies aanvaardden, en ligt dus lager dan het Google-verkeer hierboven.';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $summary = app(Ga4Collector::class)->summary();

        if (! $summary) {
            return [Stat::make('Nog geen cijfers', '—')->description('Klik op "Analytics verversen" om de historiek in te lezen.')->color('gray')];
        }

        $c = $summary['current'];
        $d = $summary['delta'];
        $compare = $summary['has_comparison'];
        $period = "{$summary['window_days']} dagen t.e.m. ".Carbon::parse($summary['period_end'])->format('d/m/Y');

        $number = fn ($v) => number_format($v, 0, ',', '.');

        return [
            $this->stat('Sessies', $number($c['sessions']), $compare ? $d['sessions'] : null, $period, $number),
            $this->stat('Bezoekers', $number($c['active_users']), $compare ? $d['active_users'] : null, 'Unieke bezoekers in deze periode.', $number),
            $this->stat('Paginaweergaven', $number($c['page_views']), $compare ? $d['page_views'] : null, 'Gemiddeld '.$this->duration($c['avg_session_seconds']).' per sessie.', $number),
            $this->stat(
                'Betrokken sessies',
                number_format($c['engagement_rate'], 1, ',', '.').' %',
                $compare ? $d['engagement_rate'] : null,
                'Sessies die langer dan 10 seconden duurden of meer dan één pagina bekeken.',
                fn ($v) => number_format($v, 1, ',', '.').' pt',
            ),
        ];
    }

    /** 95 seconden → "1 m 35 s". */
    protected function duration(int|float $seconds): string
    {
        $seconds = (int) round($seconds);

        if ($seconds < 60) {
            return $seconds.' s';
        }

        return intdiv($seconds, 60).' m '.($seconds % 60).' s';
    }

    /** Positieve delta = vooruitgang. */
    protected function stat(string $label, string $value, int|float|null $delta, string $help, \Closure $format): Stat
    {
        $stat = Stat::make($label, $value)->extraAttributes(['title' => $help]);

        if ($delta === null) {
            return $stat->description($help)->color('gray');
        }

        if ((float) $delta === 0.0) {
            return $stat->description('Gelijk aan de periode ervoor')->color('gray');
        }

        return $stat
            ->description(($delta > 0 ? '+' : '−').$format(abs($delta)).' t.o.v. de periode ervoor')
            ->descriptionIcon($delta > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
            ->color($delta > 0 ? 'success' : 'danger');
    }
}
