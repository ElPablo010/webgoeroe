<x-layouts.site
    :title="$seo['title']"
    :description="$seo['description']"
    :canonical="$seo['canonical']"
    :robots="$seo['robots']"
    :image="$seo['image']"
    :image-alt="$seo['imageAlt']"
    :image-width="$seo['imageWidth']"
    :image-height="$seo['imageHeight']"
    :type="$seo['type']"
    :schema="$seo['schema']"
    :page="$page"
>
    @php
        // Editorial sectienummer ("01", "02", …) automatisch afgeleid uit de volgorde.
        // De hero telt niet mee en krijgt geen nummer; alle overige secties worden
        // doorlopend genummerd, ongeacht of ze later van plaats wisselen.
        $sectionNumber = 0;

        // De header is fixed (zweeft over de content). Een hero compenseert dat
        // met eigen top-padding; begint een pagina zonder hero, dan zou de eerste
        // sectie onder de menubalk schuiven. Compenseer met een spacer die de
        // achtergrondkleur van de eerste sectie overneemt, zodat er geen andere
        // kleurstrook boven de sectie verschijnt. Mobiel hoger: daar neemt de
        // zwevende menubalk relatief meer ruimte in.
        $firstSection   = $page->sections->first();
        $startsWithHero = optional($firstSection)->section_type === 'hero';
        $spacerBg       = \App\Filament\Schemas\Sections\SectionBackground::classes($firstSection?->content['background'] ?? null);
    @endphp

    @unless ($startsWithHero)
        <div class="h-24 md:h-16 lg:h-20 {{ $spacerBg }}"></div>
    @endunless

    <div>
    @foreach ($page->sections as $section)
        @php
            $componentName = 'site.sections.' . str_replace('_', '-', $section->section_type);
            $content = $section->content ?? [];
            $anchorId = $content['section_id'] ?? null;

            $content['is_first'] = $loop->first;

            if ($section->section_type === 'hero') {
                unset($content['number']);
            } else {
                $content['number'] = str_pad((string) (++$sectionNumber), 2, '0', STR_PAD_LEFT);
            }
        @endphp

        @if ($anchorId)
            {{-- Anchor target net vóór de sectie. scroll-mt compenseert de fixed header. --}}
            <div id="{{ $anchorId }}" class="scroll-mt-32"></div>
        @endif

        <x-dynamic-component
            :component="$componentName"
            :section="$section"
            :content="$content"
        />
    @endforeach
    </div>
</x-layouts.site>
