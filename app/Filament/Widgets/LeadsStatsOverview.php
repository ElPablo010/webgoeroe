<?php

namespace App\Filament\Widgets;

use App\Support\LeadStats;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * De kop-cijfers van het Leads-scherm: deze maand t.o.v. het doel, de laatste
 * 28 dagen t.o.v. de 28 daarvoor, het totaal sinds de meting en het grootste
 * kanaal. Enkel op de Leads-pagina — niet op het algemene dashboard.
 */
class LeadsStatsOverview extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Leads';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $s = LeadStats::summary();
        $channels = LeadStats::byChannel();
        $top = $channels[0] ?? null;

        $month = Stat::make('Deze maand', (string) $s['thisMonth'])->descriptionIcon('heroicon-o-flag');
        if ($s['goal'] !== null) {
            $reached = $s['thisMonth'] >= $s['goal'];
            $month->description("Doel: {$s['goal']} per maand")->color($reached ? 'success' : 'warning');
        } else {
            $month->description('Stel hieronder een maanddoel in')->color('gray');
        }

        $delta = $s['last28'] - $s['previous28'];
        $recent = Stat::make('Laatste 28 dagen', (string) $s['last28']);
        if ($s['previous28'] === 0 && $s['last28'] === 0) {
            $recent->description('Nog geen leads gemeten')->color('gray');
        } elseif ($delta === 0) {
            $recent->description('Gelijk aan de 28 dagen ervoor')->color('gray');
        } else {
            $recent->description(($delta > 0 ? '+' : '') . $delta . ' t.o.v. de 28 dagen ervoor')
                ->descriptionIcon($delta > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($delta > 0 ? 'success' : 'danger');
        }

        $total = Stat::make('Totaal gemeten', (string) $s['total'])->color('primary');
        if ($s['liveSince'] !== null) {
            $total->description('Live sinds ' . $s['liveSince']->format('d/m/Y')
                . ($s['baseline'] !== null ? " — voordien ±{$s['baseline']}/maand (opgave)" : ''));
        } elseif ($s['baseline'] !== null) {
            $total->description("Vóór de meting: ±{$s['baseline']} per maand (opgave)");
        } else {
            $total->description('Sinds de meting begon');
        }

        return [
            $month,
            $recent,
            $total,
            Stat::make('Grootste kanaal (90 d.)', $top['label'] ?? '—')
                ->description($top ? "{$top['count']} " . ($top['count'] === 1 ? 'lead' : 'leads') : 'Nog geen herkomst gemeten')
                ->descriptionIcon('heroicon-o-arrow-right-circle')
                ->color($top ? 'primary' : 'gray'),
        ];
    }
}
