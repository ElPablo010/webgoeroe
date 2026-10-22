<?php

namespace App\Support;

use App\Services\GoHighLevel\ContactFields;
use Illuminate\Support\Str;

/**
 * De velden van het formulier bij de eigen kalender (boeking-blok, weergave "own").
 * Ze staan per boeking-sectie in `content.form_fields`; elk veld is gekoppeld aan een
 * GoHighLevel-veld via `target` (zie ContactFields). Zonder ingestelde velden geldt
 * defaults(). Een veld voor het e-mailadres is er altijd: zonder kan GHL geen contact maken.
 *
 * Een veld: ['key' => 'abc123', 'label' => 'Voornaam', 'type' => 'text|email|tel|url|textarea',
 *            'required' => true, 'target' => 'first_name', 'placeholder' => null]
 */
class BookingForm
{
    public const TYPES = [
        'text' => 'Tekst (één regel)',
        'textarea' => 'Groot tekstvak',
        'email' => 'E-mailadres',
        'tel' => 'Telefoonnummer',
        'url' => 'Website-adres',
    ];

    /** @return list<array<string, mixed>> */
    public static function defaults(): array
    {
        return [
            ['key' => 'first_name', 'label' => 'Voornaam', 'type' => 'text', 'required' => true, 'target' => 'first_name', 'placeholder' => null],
            ['key' => 'last_name', 'label' => 'Achternaam', 'type' => 'text', 'required' => true, 'target' => 'last_name', 'placeholder' => null],
            ['key' => 'email', 'label' => 'E-mailadres', 'type' => 'email', 'required' => true, 'target' => 'email', 'placeholder' => null],
            ['key' => 'phone', 'label' => 'Telefoon', 'type' => 'tel', 'required' => false, 'target' => 'phone', 'placeholder' => null],
            ['key' => 'website', 'label' => 'Website', 'type' => 'url', 'required' => false, 'target' => 'website', 'placeholder' => 'www.jouwbedrijf.be'],
        ];
    }

    /**
     * De velden van een sectie, opgeschoond (geldige types, unieke sleutels, altijd een e-mailveld).
     *
     * @return list<array<string, mixed>>
     */
    public static function fields(array $content): array
    {
        $fields = collect($content['form_fields'] ?? [])
            ->filter(fn ($field) => is_array($field) && filled($field['label'] ?? null) && filled($field['target'] ?? null))
            ->values();

        if ($fields->isEmpty()) {
            return self::defaults();
        }

        $seen = [];
        $fields = $fields->map(function (array $field) use (&$seen): array {
            $key = Str::slug((string) ($field['key'] ?? ''), '_') ?: Str::random(8);
            while (in_array($key, $seen, true)) {
                $key .= '_'.Str::lower(Str::random(3));
            }
            $seen[] = $key;

            $type = array_key_exists($field['type'] ?? '', self::TYPES) ? $field['type'] : 'text';

            return [
                'key' => $key,
                'label' => trim((string) $field['label']),
                // Het e-mailveld is altijd een verplicht e-mailadres.
                'type' => $field['target'] === 'email' ? 'email' : $type,
                'required' => $field['target'] === 'email' || (bool) ($field['required'] ?? false),
                'target' => (string) $field['target'],
                'placeholder' => filled($field['placeholder'] ?? null) ? (string) $field['placeholder'] : null,
            ];
        })->all();

        if (! collect($fields)->contains('target', 'email')) {
            $fields[] = ['key' => 'email', 'label' => 'E-mailadres', 'type' => 'email', 'required' => true, 'target' => 'email', 'placeholder' => null];
        }

        return $fields;
    }

    /** Is een target geldig (standaardveld, eigen GHL-veld of enkel in de afspraak)? */
    public static function isValidTarget(string $target): bool
    {
        return array_key_exists($target, ContactFields::STANDARD)
            || $target === ContactFields::APPOINTMENT
            || preg_match('/^custom:[A-Za-z0-9]{6,40}$/', $target) === 1;
    }
}
