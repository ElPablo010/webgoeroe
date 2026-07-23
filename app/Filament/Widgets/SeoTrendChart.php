<?php

namespace App\Filament\Widgets;

use App\Support\SeoStats;
use Filament\Widgets\ChartWidget;

/**
 * Verloop van de organische zichtbaarheid over alle snapshots heen.
 *
 * De gebruiker kiest welke reeks getoond wordt: verkeer, keyword-aantal of
 * top-10-posities. Die staan op sterk verschillende schalen (etv in duizenden,
 * top-10 in tientallen), dus ze samen op één as zetten maakt de kleinste reeks
 * onleesbaar — vandaar een filter i.p.v. drie datasets.
 */
class SeoTrendChart extends ChartWidget
{
    protected ?string $heading = 'Verloop';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    public ?string $filter = 'etv';

    /**
     * @return array<string, string>
     */
    protected function getFilters(): ?array
    {
        return [
            'etv' => 'Geschat verkeer',
            'keywords' => 'Keywords in Google',
            'top10' => 'Posities in top 10',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $snapshots = SeoStats::snapshots();

        $values = $snapshots->map(fn ($snapshot) => match ($this->filter) {
            'keywords' => $snapshot->organic_keywords_count,
            'top10' => $snapshot->top10,
            default => $snapshot->organic_etv,
        });

        return [
            'datasets' => [[
                'label' => $this->getFilters()[$this->filter] ?? 'Geschat verkeer',
                'data' => $values->values()->all(),
                'borderColor' => 'rgb(59, 130, 246)',
                'backgroundColor' => 'rgba(59, 130, 246, 0.12)',
                'fill' => true,
                'tension' => 0.3,
            ]],
            'labels' => $snapshots->map(fn ($snapshot) => $snapshot->captured_at->format('d/m'))->values()->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true]],
        ];
    }
}
