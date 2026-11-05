<?php

use App\Models\CaseStudy;
use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * De site-brede CTA (Instellingen → Algemeen, sleutel `cta`, één knop) wordt
 * de banner van de modules blog en cases (Instellingen → Blog en → Cases,
 * sleutels `blog_cta` en `cases_cta`, primaire + secundaire knop).
 *
 * - Beide nieuwe instellingen krijgen titel, tekst en de knop van de oude als
 *   primaire knop. De secundaire knop (offerte) vult Pieter zelf in zodra die
 *   pagina bestaat.
 * - Een case met een eigen knoptekst of knop-URL krijgt die als eigen knop
 *   (content.cta.buttons); wat leeg was, komt uit de oude instelling.
 */
return new class extends Migration
{
    public function up(): void
    {
        $old = Setting::get('cta');

        if (! is_array($old)) {
            return;
        }

        $button = $this->button($old['button_label'] ?? null, $old);

        foreach (['blog_cta', 'cases_cta'] as $key) {
            if (Setting::query()->where('key', $key)->exists()) {
                continue;
            }

            Setting::set($key, [
                'enabled' => true,
                'title' => $old['title'] ?? null,
                'body' => $old['body'] ?? null,
                'buttons' => $button ? [$button] : [],
            ]);
        }

        foreach (CaseStudy::query()->cursor() as $case) {
            $content = $case->content;
            $cta = $content['cta'] ?? null;

            if (! is_array($cta) || (! array_key_exists('button_label', $cta) && ! array_key_exists('button_url', $cta))) {
                continue;
            }

            if (filled($cta['button_label'] ?? null) || filled($cta['button_url'] ?? null)) {
                $link = filled($cta['button_url'] ?? null)
                    ? ['link_type' => 'url', 'page_id' => null, 'href' => $cta['button_url']]
                    : $old;
                $own = $this->button(($cta['button_label'] ?? null) ?: ($old['button_label'] ?? null), $link);
                $cta['buttons'] = $own ? [$own] : [];
            }

            unset($cta['button_label'], $cta['button_url']);
            $content['cta'] = $cta;
            $case->content = $content;
            $case->save();
        }
    }

    public function down(): void
    {
        // Content-migratie: de oude instelling `cta` blijft staan; bewust een no-op.
    }

    /**
     * @param  array<string, mixed>  $link
     * @return array<string, mixed>|null
     */
    private function button(?string $label, array $link): ?array
    {
        if (blank($label)) {
            return null;
        }

        return [
            'label' => $label,
            'variant' => 'primary',
            'link_type' => $link['link_type'] ?? 'url',
            'page_id' => $link['page_id'] ?? null,
            'href' => $link['href'] ?? null,
        ];
    }
};
