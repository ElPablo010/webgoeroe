<?php

use App\Models\PageSection;
use Illuminate\Database\Migrations\Migration;

/**
 * Afspraaktitel "Website Bottleneck Scan" voor de agenda "Bottleneck Scan
 * Websites" (eigen kalender), in plaats van agendanaam + naam van de klant.
 */
return new class extends Migration
{
    private const CALENDAR = 'knxGUwQTCwM1iGpZ5Sgz';

    public function up(): void
    {
        PageSection::query()->where('section_type', 'booking')->get()
            ->filter(fn (PageSection $section) => ($section->content['provider'] ?? null) === 'ghl'
                && str_contains((string) ($section->content['url'] ?? ''), self::CALENDAR))
            ->each(fn (PageSection $section) => $section->update(['content' => [
                ...$section->content,
                'appointment_title' => $section->content['appointment_title'] ?? 'Website Bottleneck Scan',
            ]]));
    }

    public function down(): void
    {
        // Bewust leeg.
    }
};
