<?php

namespace App\Filament\Widgets;

use App\Support\LeadStats;
use Filament\Widgets\ChartWidget;

/**
 * Leads per maand (laatste 12), met het maanddoel als stippellijn en de
 * livegang-maand gemarkeerd in het label. Enkel op de Leads-pagina.
 */
class LeadsTrendChart extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Leads per maand';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $monthly = LeadStats::monthly(12);
        $goal = LeadStats::summary()['goal'];

        $datasets = [[
            'label' => 'Leads',
            'data' => array_column($monthly, 'count'),
            'backgroundColor' => 'rgba(124, 58, 237, 0.55)',
            'borderColor' => 'rgb(124, 58, 237)',
            'borderWidth' => 1,
        ]];

        if ($goal !== null) {
            $datasets[] = [
                'type' => 'line',
                'label' => 'Doel',
                'data' => array_fill(0, count($monthly), $goal),
                'borderColor' => 'rgb(16, 185, 129)',
                'borderDash' => [6, 4],
                'pointRadius' => 0,
                'fill' => false,
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => array_map(fn ($m) => $m['label'] . ($m['live'] ? ' ▲ live' : ''), $monthly),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }
}
