<?php

namespace App\Filament\Widgets;

use App\Services\GscCollector;
use App\Support\LeadStats;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

/**
 * Weekverloop van het gemeten Google-verkeer (laatste 26 weken), met de
 * livegang-week gemarkeerd. Eén reeks tegelijk: clicks, vertoningen en
 * positie staan op te verschillende schalen voor één as.
 */
class GscTrendChart extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Verloop per week';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    public ?string $filter = 'clicks';

    /**
     * @return array<string, string>
     */
    protected function getFilters(): ?array
    {
        return [
            'clicks' => 'Clicks',
            'impressions' => 'Vertoningen',
            'position' => 'Gem. positie',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $weeks = app(GscCollector::class)->weeklyTrend(26);
        $liveWeek = class_exists(LeadStats::class) ? LeadStats::liveSince()?->startOfWeek()->toDateString() : null;

        return [
            'datasets' => [[
                'label' => $this->getFilters()[$this->filter] ?? 'Clicks',
                'data' => array_map(fn ($w) => $w[$this->filter] ?? null, $weeks),
                'borderColor' => 'rgb(124, 58, 237)',
                'backgroundColor' => 'rgba(124, 58, 237, 0.12)',
                'fill' => $this->filter !== 'position',
                'tension' => 0.3,
            ]],
            'labels' => array_map(fn ($w) => Carbon::parse($w['date'])->format('d/m') . ($w['date'] === $liveWeek ? ' ▲ live' : ''), $weeks),
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
            'scales' => ['y' => $this->filter === 'position'
                ? ['reverse' => true, 'title' => ['display' => true, 'text' => 'positie (1 = bovenaan)']]
                : ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }
}
