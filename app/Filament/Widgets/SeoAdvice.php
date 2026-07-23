<?php

namespace App\Filament\Widgets;

use App\Support\SeoStats;
use Filament\Widgets\Widget;

/**
 * Het laatste AI-advies uit het weekrapport, als markdown gerenderd.
 *
 * Verbergt zichzelf zolang er nog geen rapport gedraaid heeft — een lege
 * kaart met "nog geen advies" voegt niets toe aan een vers dashboard.
 */
class SeoAdvice extends Widget
{
    protected string $view = 'filament.widgets.seo-advice';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return filled(SeoStats::latestReport()?->advice);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $report = SeoStats::latestReport();

        return [
            'advice' => $report?->advice,
            'capturedAt' => $report?->captured_at,
        ];
    }
}
