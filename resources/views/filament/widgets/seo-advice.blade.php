{{--
    Het AI-advies komt als markdown uit SeoAdvisorService. Str::markdown() zet
    het om naar HTML; die output is model-gegenereerd, dus we laten CommonMark
    de HTML escapen (default) i.p.v. raw HTML toe te laten.

    Typografie staat inline: de app-Tailwind (incl. de typography-plugin) wordt
    niet in het Filament-panel geladen, dus prose-klassen doen hier niets.
--}}
<x-filament-widgets::widget>
    <x-filament::section
        :heading="'Advies'"
        :description="$capturedAt ? 'Gegenereerd op ' . $capturedAt->format('d/m/Y') : null"
        collapsible
    >
        <div class="text-gray-700 dark:text-gray-300"
             style="line-height: 1.65; font-size: 0.875rem;">
            <style>
                .seo-advice h1, .seo-advice h2, .seo-advice h3 {
                    font-weight: 600;
                    margin: 1.25em 0 0.5em;
                    line-height: 1.3;
                }
                .seo-advice h1 { font-size: 1.125rem; }
                .seo-advice h2 { font-size: 1rem; }
                .seo-advice h3 { font-size: 0.9375rem; }
                .seo-advice > *:first-child { margin-top: 0; }
                .seo-advice p { margin: 0.75em 0; }
                .seo-advice ul, .seo-advice ol { margin: 0.75em 0; padding-left: 1.5em; }
                .seo-advice ul { list-style: disc; }
                .seo-advice ol { list-style: decimal; }
                .seo-advice li { margin: 0.3em 0; }
                .seo-advice strong { font-weight: 600; }
                .seo-advice a { text-decoration: underline; }
            </style>

            <div class="seo-advice">
                {!! \Illuminate\Support\Str::markdown($advice) !!}
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
