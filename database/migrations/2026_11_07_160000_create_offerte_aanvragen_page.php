<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Webgoeroe\Core\Core;
use Webgoeroe\Core\Support\CustomForm;

/**
 * Tweede spoor naast de Bottleneck Scan (beslist 7/11/2026): wie al weet wat hij
 * wil, vraagt meteen een offerte aan op /offerte-aanvragen.
 *
 * - Het formulier "Offerteaanvraag" (core 0.15.13, Website → Formulieren) krijgt de
 *   vragen van De WebGoeroe: diensten, huidige website, timing en budget.
 * - De pagina /offerte-aanvragen met dat formulier; aanvragen krijgen in GHL de
 *   tag `offerte-aangevraagd`.
 * - Knoppen: op Websites & Conversie wordt de offerte de eerste knop in de hero;
 *   de homepage-hero en de afsluitende CTA van de home en de dienstpagina's
 *   krijgen "Vraag een offerte aan" als tweede knop.
 *
 * Content-migratie zodat alles met een gewone deploy live komt, op de live
 * inhoud. Knoppen die al naar de offertepagina wijzen, komen er niet dubbel bij.
 */
return new class extends Migration
{
    private const SLUG = 'offerte-aanvragen';

    private const LABEL = 'Vraag een offerte aan';

    /** Pagina's waarvan de afsluitende CTA een tweede knop krijgt. */
    private const CTA_PAGES = ['home', 'websites-conversie', 'sales-automation', 'business-automation', 'leadgeneratie'];

    public function up(): void
    {
        // Enkel op een site met inhoud (lokaal/live), niet in tests of op een verse installatie.
        $scan = Page::query()->where('slug', 'bottleneck-scan')->first();
        $formModel = Core::model('form');

        if (! $scan || ! Schema::hasTable((new $formModel)->getTable())) {
            return;
        }

        $form = $formModel::query()->where('name', 'Offerteaanvraag')->first() ?? new $formModel(['name' => 'Offerteaanvraag']);
        $form->fill([
            'tab_label' => 'Offerte aanvragen',
            'button_label' => 'Vraag je offerte aan',
            'success_message' => 'We bekijken je aanvraag en nemen zo snel mogelijk contact met je op, uiterlijk binnen 1 à 2 werkdagen.',
            'fields' => CustomForm::fields([
                CustomForm::field('Naam', 'text', true, 'half', target: 'name'),
                CustomForm::field('Bedrijf', 'text', false, 'half', target: 'company'),
                CustomForm::field('E-mailadres', 'email', true, 'half', target: 'email'),
                CustomForm::field('Telefoon', 'tel', true, 'half', target: 'phone'),
                CustomForm::field('Waarvoor wil je een offerte?', 'checkboxes', true, 'full', key: 'diensten', options: [
                    'Nieuwe website',
                    'Website vernieuwen',
                    'Webshop',
                    'AI-telefoonassistent',
                    'AI-chatbot',
                    'Automatisering of koppeling',
                    'Iets anders',
                ]),
                CustomForm::field('Wat wil je bereiken?', 'textarea', true, 'full', key: 'message', placeholder: 'Vertel kort wat je zoekt, wat er nu misloopt of wat het moet opleveren.'),
                CustomForm::field('Je huidige website', 'url', false, 'half', target: 'website', placeholder: 'www.jouwbedrijf.be'),
                CustomForm::field('Wanneer wil je starten?', 'select', false, 'half', key: 'timing', options: ['Zo snel mogelijk', 'Binnen 1 à 3 maanden', 'Later', 'Nog niet zeker']),
                CustomForm::field('Welk budget heb je ongeveer voor ogen?', 'select', false, 'half', key: 'budget', options: ['Minder dan € 2.500', '€ 2.500 tot € 5.000', '€ 5.000 tot € 10.000', 'Meer dan € 10.000', 'Weet ik nog niet']),
                CustomForm::field('Bijlage (bv. een plan of voorbeeld)', 'file', false, 'half', key: 'attachments'),
            ]),
        ]);
        $form->save();

        $page = Page::query()->where('slug', self::SLUG)->first() ?? $this->createPage($form);

        $quote = ['label' => self::LABEL, 'page_id' => $page->id, 'variant' => 'ghost', 'link_type' => 'page'];

        $this->updateSections('websites-conversie', 'hero', fn (array $ctas): array => [
            ['label' => 'Vraag je offerte aan', 'page_id' => $page->id, 'variant' => 'primary', 'link_type' => 'page'],
            ['label' => 'Of plan eerst een gratis Bottleneck Scan', 'page_id' => $scan->id, 'variant' => 'ghost', 'link_type' => 'page'],
        ], $page->id);

        $this->updateSections('home', 'hero', fn (array $ctas): array => [...$ctas, $quote], $page->id);

        foreach (self::CTA_PAGES as $slug) {
            $this->updateSections($slug, 'cta', fn (array $ctas): array => [...$ctas, $quote], $page->id);
        }
    }

    private function createPage($form): Page
    {
        $page = Page::query()->create([
            'title' => 'Offerte aanvragen',
            'slug' => self::SLUG,
            'locale' => 'nl',
            'published' => true,
            'meta_title' => 'Offerte aanvragen | De WebGoeroe',
            'meta_description' => 'Weet je al wat je nodig hebt? Vraag een offerte aan voor je website, webshop, AI-telefoonassistent of automatisering. Je krijgt een voorstel met een vaste prijs.',
        ]);

        $page->sections()->create([
            'section_type' => 'form',
            'position' => 0,
            'locale' => 'nl',
            'content' => [
                'eyebrow' => 'Offerte',
                'heading' => 'Vraag je offerte aan',
                'intro' => '<p>Weet je al wat je nodig hebt? Vertel ons kort wat je zoekt. We bekijken je aanvraag, stemmen de details met je af en bezorgen je een voorstel met een vaste prijs.</p>'
                    .'<p>Twijfel je nog waar je best begint? Plan dan eerst een gratis <a href="/bottleneck-scan">Bottleneck Scan</a>.</p>',
                'form_type' => $form->submissionType(),
                'form_layout' => 'right',
                'background' => null,
                'section_id' => null,
                'contact_email' => 'hello@dewebgoeroe.be',
                'contact_phone' => '+32 456 95 25 54',
                'ghl' => ['tags' => ['offerte-aangevraagd'], 'source' => 'Website – Offerteaanvraag'],
            ],
        ]);

        return $page;
    }

    /**
     * Past de knoppen aan van elk blok van dat type op die pagina, tenzij er al
     * een knop naar de offertepagina staat.
     *
     * @param  Closure(array): array  $change
     */
    private function updateSections(string $slug, string $type, Closure $change, int $quotePageId): void
    {
        $page = Page::query()->where('slug', $slug)->first();

        $page?->sections()->where('section_type', $type)->get()->each(function ($section) use ($change, $quotePageId): void {
            $content = (array) $section->content;
            $ctas = array_values((array) ($content['ctas'] ?? []));

            if ($ctas === [] || collect($ctas)->contains(fn ($cta): bool => (int) ($cta['page_id'] ?? 0) === $quotePageId)) {
                return;
            }

            $content['ctas'] = $change($ctas);
            $section->content = $content;
            $section->save();
        });
    }
};
