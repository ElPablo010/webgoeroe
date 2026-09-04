<?php

namespace App\Filament\Widgets;

use App\Services\GscCollector;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Gemeten Google-verkeer van de laatste 28 dagen, vergeleken met de 28 dagen
 * ervoor. Enkel op de Verkeer-pagina — niet op het algemene dashboard.
 */
class GscStatsOverview extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Google-verkeer';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $summary = app(GscCollector::class)->summary();

        if (! $summary) {
            return [Stat::make('Nog geen cijfers', '—')->description('Klik op "Ververs nu" om de eerste 16 maanden in te lezen.')->color('gray')];
        }

        $c = $summary['current'];
        $d = $summary['delta'];
        $compare = $summary['has_comparison'];
        $period = "{$summary['window_days']} dagen t.e.m. " . \Illuminate\Support\Carbon::parse($summary['period_end'])->format('d/m/Y');

        return [
            $this->stat('Clicks', number_format($c['clicks'], 0, ',', '.'), $compare ? $d['clicks'] : null, $period, fn ($v) => number_format($v, 0, ',', '.')),
            $this->stat('Vertoningen', number_format($c['impressions'], 0, ',', '.'), $compare ? $d['impressions'] : null, $period, fn ($v) => number_format($v, 0, ',', '.')),
            $this->stat('CTR', number_format($c['ctr'], 1, ',', '.') . ' %', $compare ? $d['ctr'] : null, 'Clicks gedeeld door vertoningen.', fn ($v) => number_format($v, 1, ',', '.') . ' pt'),
            $this->stat('Gem. positie', $c['position'] !== null ? number_format($c['position'], 1, ',', '.') : '—', $compare ? $d['position'] : null, 'Gewogen op vertoningen; lager is beter.', fn ($v) => number_format($v, 1, ',', '.')),
        ];
    }

    /** Positieve delta = vooruitgang (de collector draait het teken van de positie al om). */
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
            ->description(($delta > 0 ? '+' : '−') . $format(abs($delta)) . ' t.o.v. de periode ervoor')
            ->descriptionIcon($delta > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
            ->color($delta > 0 ? 'success' : 'danger');
    }
}
