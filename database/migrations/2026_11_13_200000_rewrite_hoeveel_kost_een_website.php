<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;

/**
 * /hoeveel-kost-een-website was een dunne pagina (hero, drie vage alinea's, FAQ
 * zonder bedragen) die niet converteerde. Nu: het antwoord meteen in de hero,
 * twee grote prijskaartjes (website voor vakmannen met speed-to-lead; website op
 * maat), keuzehulp, aanpak, cases, FAQ met bedragen en een afsluitende CTA.
 *
 * Prijzen volgens WebGoeroe/wiki/aanbod.md: Speed to Lead €297/maand zonder
 * setup (incl. Reviews en gratis standaardsite), website op maat €2.500 +
 * Onderhoud €47/maand, groter werk €75/u. Content-migratie: komt live met een
 * gewone deploy. Maakt de pagina aan als ze er niet is (lokaal), anders vervangt
 * ze de secties van de bestaande pagina.
 */
return new class extends Migration
{
    private const SLUG = 'hoeveel-kost-een-website';

    public function up(): void
    {
        // Enkel op een site met inhoud (lokaal/live), niet in tests of op een verse installatie.
        $scan = Page::query()->where('slug', 'bottleneck-scan')->first();
        if (! $scan) {
            return;
        }

        $quote = Page::query()->where('slug', 'offerte-aanvragen')->first();
        $vakman = Page::query()->where('slug', 'speed-to-lead-elektriciens')->first();

        $page = Page::query()->where('slug', self::SLUG)->first() ?? Page::query()->create([
            'title' => 'Hoeveel kost een website?',
            'slug' => self::SLUG,
            'locale' => 'nl',
            'published' => true,
        ]);

        $page->fill([
            'meta_title' => 'Hoeveel kost een website? Vanaf €297 | De WebGoeroe',
            'meta_description' => 'Een website voor vakmannen vanaf €297 per maand, een website op maat voor €2.500 eenmalig. Vaste prijzen, geen verrassingen achteraf.',
        ])->save();

        $link = fn (?Page $target, string $label, string $variant): ?array => $target
            ? ['label' => $label, 'page_id' => $target->id, 'variant' => $variant, 'link_type' => 'page']
            : null;

        $scanPrimary = $link($scan, 'Plan je gratis Bottleneck Scan', 'primary');
        $quoteGhost = $link($quote, 'Vraag je offerte aan', 'ghost');
        $ctas = array_values(array_filter([$scanPrimary, $quoteGhost]));

        $cardLink = fn (?Page $target, string $label): array => $target
            ? ['cta_label' => $label, 'page_id' => $target->id, 'link_type' => 'page']
            : [];

        $sections = [
            ['hero', [
                'height' => 'tall',
                'eyebrow' => 'Prijzen',
                'heading' => 'Hoeveel kost een website?',
                'subtitle' => 'Een website voor vakmannen met speed-to-lead systeem kost €297 per maand, zonder opstartkost. Een website op maat kost €2.500 eenmalig, plus €47 per maand voor hosting, onderhoud en support. Vaste prijzen, vooraf afgesproken.',
                'ctas' => $ctas,
            ]],
            ['cards', [
                'eyebrow' => 'Twee manieren',
                'heading' => 'Kies de website die bij je past',
                'columns' => 2,
                'background' => 'dark',
                'section_id' => 'prijzen',
                'cards' => [
                    [
                        'media_type' => 'icon',
                        'icon' => 'hammer',
                        'title' => 'Website voor vakmannen',
                        'subtitle' => '€297 per maand, zonder opstartkost',
                        'description' => 'Voor elektriciens, dakwerkers, loodgieters en andere vakmannen die geen enkele aanvraag meer willen missen.',
                        'features' => [
                            'Gratis standaardwebsite (±5 pagina\'s)',
                            'Speed to Lead: elke aanvraag direct opgevolgd',
                            'Reviews-systeem voor meer Google-reviews',
                            'Maandelijks opzegbaar, geen contract',
                        ],
                    ] + $cardLink($vakman, 'Bekijk het systeem voor vakmannen'),
                    [
                        'media_type' => 'icon',
                        'icon' => 'pen-tool',
                        'title' => 'Website op maat',
                        'subtitle' => '€2.500 eenmalig, daarna €47 per maand',
                        'description' => 'Voor bedrijven die een website willen die volledig op hun zaak is gebouwd en bezoekers omzet in aanvragen.',
                        'features' => [
                            'Ontwerp en teksten op maat van jouw bedrijf',
                            'Gebouwd om aanvragen op te leveren',
                            'Zelf pagina\'s en blogs beheren',
                            'Basis voor vindbaarheid in Google en AI',
                            'Hosting, onderhoud en support inbegrepen',
                            'Vaste prijs, vooraf afgesproken',
                        ],
                    ] + $cardLink($scan, 'Plan een gratis Bottleneck Scan'),
                ],
            ]],
            ['text', [
                'heading' => 'Welke past bij jou?',
                'background' => 'light',
                'body' => '<p><strong>Ben je vakman</strong> en wil je vooral geen aanvragen meer missen? Dan is de website voor vakmannen de snelste en voordeligste start.</p>'
                    .'<p><strong>Heb je andere wensen</strong>, zoals meer pagina\'s, een eigen aanpak of koppelingen? Dan bouwen we een website op maat. Groter werk buiten het pakket rekenen we aan €75 per uur, altijd vooraf afgesproken.</p>'
                    .'<p>Twijfel je? Plan een gratis Bottleneck Scan van 30 minuten. We kijken samen wat bij jou past, zonder verkooppraatje.</p>',
            ]],
            ['process_steps', [
                'eyebrow' => 'Zo werkt het',
                'heading' => 'Van gesprek tot online in drie stappen',
                'background' => 'dark',
                'section_id' => 'aanpak',
                'steps' => [
                    ['title' => 'Gratis Bottleneck Scan', 'description' => 'In 30 minuten luisteren we waar je website nu stokt. Geen verkooppraatje.'],
                    ['title' => 'Voorstel met vaste prijs', 'description' => 'Je krijgt een duidelijk voorstel. Wat je ziet, is wat je betaalt.'],
                    ['title' => 'We bouwen en zetten online', 'description' => 'Een nieuwe website duurt 3 tot 6 weken. Je volgt mee en keurt goed voor we live gaan.'],
                ],
            ]],
            ['cases_grid', [
                'eyebrow' => 'Resultaat',
                'heading' => 'Websites die aantoonbaar meer opleveren',
                'background' => 'light',
                'limit' => 3,
                'filter_tags' => ['Websites & Conversie'],
                'cta' => [['href' => '/cases', 'label' => 'Bekijk alle cases', 'variant' => 'secondary', 'link_type' => 'url']],
            ]],
            ['faq', [
                'heading' => 'Veelgestelde vragen',
                'background' => 'dark',
                'section_id' => 'faq',
                'items' => [
                    ['question' => 'Hoeveel kost een website?', 'answer' => 'Een website voor vakmannen kost €297 per maand, zonder opstartkost. Een website op maat kost €2.500 eenmalig, plus €47 per maand voor hosting, onderhoud en support.'],
                    ['question' => 'Zit er een contract aan vast?', 'answer' => 'Het maandbedrag is maandelijks opzegbaar, zonder minimumduur. Bij een website op maat leggen we alles vooraf vast in het voorstel.'],
                    ['question' => 'Wat zit er in de €47 per maand?', 'answer' => 'Hosting, onderhoud en support. Wil je er meer bij, zoals de Groei-module voor zichtbaarheid in Google en opvolging van aanvragen? Dan komt daar een maandbedrag bij, bijvoorbeeld €50 extra voor Groei.'],
                    ['question' => 'Waarom een vaste prijs?', 'answer' => 'Omdat je vooraf wil weten waar je aan toe bent. Je krijgt een voorstel met een vaste prijs en geen verrassingen achteraf.'],
                    ['question' => 'Is een goedkope website niet genoeg?', 'answer' => 'Een website die niemand overtuigt, levert niets op, hoe weinig hij ook kost. Daarom bouwen we elke website om aanvragen op te leveren, niet enkel om er mooi uit te zien.'],
                    ['question' => 'Wat als ik meer wil dan het pakket?', 'answer' => 'Groter werk buiten het pakket rekenen we aan €75 per uur. Dat spreken we altijd vooraf af, zodat je niet verrast wordt.'],
                    ['question' => 'Hoe lang duurt het bouwen van een website?', 'answer' => 'Een nieuwe website op maat duurt 3 tot 6 weken. Na je akkoord op het voorstel plannen we samen de oplevering.'],
                ],
            ]],
            ['cta', [
                'heading' => 'Klaar om te weten wat je krijgt?',
                'intro' => 'Plan een gratis Bottleneck Scan van 30 minuten. We kijken samen wat bij jou past en daarna krijg je een voorstel met een vaste prijs.',
                'ctas' => $ctas,
            ]],
        ];

        $page->sections()->delete();
        foreach ($sections as $position => [$type, $content]) {
            $page->sections()->create(['section_type' => $type, 'position' => $position, 'locale' => 'nl', 'content' => $content]);
        }
    }
};
