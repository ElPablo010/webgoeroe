<?php

use App\Models\Page;
use App\Support\AiCoaching;
use Illuminate\Database\Migrations\Migration;

/**
 * AI-coaching: bedrijfsnaam en btw-nummer als optionele velden in het boekingsformulier,
 * zodat de factuur voor een betaalde sessie (list_payments in de MCP-server) ze kent.
 * Bestaande velden blijven staan; wie de velden al heeft, krijgt ze niet dubbel.
 */
return new class extends Migration
{
    private const FIELDS = [
        ['label' => 'Bedrijfsnaam', 'placeholder' => null],
        ['label' => 'Btw-nummer', 'placeholder' => 'BE0123456789'],
    ];

    public function up(): void
    {
        $page = Page::query()->where('slug', AiCoaching::SLUG)->first();

        if (! $page) {
            return;
        }

        foreach ($page->sections()->where('section_type', 'booking')->get() as $section) {
            $content = $section->content;
            $fields = array_values((array) ($content['agenda_fields'] ?? []));

            if ($fields === []) {
                continue;
            }

            $labels = array_map(fn ($field) => mb_strtolower((string) ($field['label'] ?? '')), $fields);

            $add = [];

            foreach (self::FIELDS as $new) {
                if (in_array(mb_strtolower($new['label']), $labels, true)) {
                    continue;
                }

                $add[] = [
                    'key' => 'x_'.str($new['label'])->slug('_'),
                    'role' => 'extra',
                    'label' => $new['label'],
                    'type' => 'text',
                    'required' => false,
                    'options' => [],
                    'placeholder' => $new['placeholder'],
                ];
            }

            // Vóór de vrije vragen (de eerste 'extra'), zodat de contactgegevens bij elkaar staan.
            $at = collect($fields)->search(fn ($field) => ($field['role'] ?? null) === 'extra');
            array_splice($fields, $at === false ? count($fields) : $at, 0, $add);

            $section->update(['content' => [...$content, 'agenda_fields' => $fields]]);
        }
    }

    public function down(): void
    {
        $page = Page::query()->where('slug', AiCoaching::SLUG)->first();

        if (! $page) {
            return;
        }

        foreach ($page->sections()->where('section_type', 'booking')->get() as $section) {
            $content = $section->content;
            $content['agenda_fields'] = array_values(array_filter(
                (array) ($content['agenda_fields'] ?? []),
                fn ($field) => ! in_array($field['label'] ?? '', array_column(self::FIELDS, 'label'), true),
            ));

            $section->update(['content' => $content]);
        }
    }
};
