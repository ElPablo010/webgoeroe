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
        $spacerBg       = \Webgoeroe\Core\Filament\Schemas\Sections\SectionBackground::classes($firstSection?->content['background'] ?? null);
    @endphp

    @unless ($startsWithHero)
        <div class="h-24 md:h-16 lg:h-20 {{ $spacerBg }}"></div>
    @endunless

    @php
        // Verborgen blokken (schakelaar "Verbergen op de site") vallen weg; de rest kan
        // in een wisselpaar zitten (App\Support\SectionSwap, "Wisselen met een ander blok").
        $visibleSections = $page->sections->reject(fn ($s) => ! empty($s->content['hidden']))->values();
        $swapGroups = \App\Support\SectionSwap::groups($visibleSections);
    @endphp

    <div>
    @foreach ($visibleSections as $section)
        @php
            // Sectieview uit dit project (components/site/sections/<type>), anders
            // de standaardview van webgoeroe/core.
            $componentName = \Webgoeroe\Core\Support\Views::section($section->section_type) ?? 'core::sections.unknown';
            $content = $section->content ?? [];
            $swap = $swapGroups[$loop->index] ?? null;

            $content['is_first'] = $loop->first;
            // Zelfde achtergrond als het vorige blok? Dan kan een blok zijn
            // bovenmarge inkorten, anders stapelen twee paddings zich op. In een
            // wisselpaar is "het vorige" voor beide blokken het blok vóór het paar.
            $bgKey = $content['background'] ?? null ?: \Webgoeroe\Core\Filament\Schemas\Sections\SectionBackground::default();
            if ($swap && ! $swap['first']) {
                $previousBg = $bgBeforeSwap ?? null;
            }
            if ($swap && $swap['first']) {
                $bgBeforeSwap = $previousBg ?? null;
            }
            $content['same_bg_as_previous'] = isset($previousBg) && $previousBg === $bgKey;
            $previousBg = $bgKey;

            if ($section->section_type === 'hero') {
                unset($content['number']);
            } else {
                $content['number'] = str_pad((string) (++$sectionNumber), 2, '0', STR_PAD_LEFT);
            }
        @endphp

        @if ($swap && $swap['first'])
            {{-- Wisselpaar: twee knoppen, altijd één van de twee blokken zichtbaar.
                 Een link naar #<anker> opent meteen het juiste blok. Bij een klik scrollt
                 de pagina bewust niet: de knoppen blijven waar ze staan, enkel wat eronder
                 staat wisselt. --}}
            <div
                data-swap-group
                class="relative scroll-mt-24"
                x-data="{ active: @js($swap['initial']), anchors: @js(array_column($swap['members'], 'anchor')) }"
                x-init="const h = location.hash.slice(1); if (anchors.includes(h)) active = h"
            >
                <div class="relative z-10 {{ \Webgoeroe\Core\Filament\Schemas\Sections\SectionBackground::classes($swap['background']) }} px-6 pt-16 md:pt-20">
                    <div class="mx-auto flex w-fit max-w-full flex-wrap justify-center gap-1 rounded-full border border-white/10 bg-white/[0.04] p-1" role="tablist">
                        @foreach ($swap['members'] as $member)
                            <button
                                type="button"
                                role="tab"
                                :aria-selected="active === @js($member['anchor'])"
                                @click="active = @js($member['anchor'])"
                                :class="active === @js($member['anchor']) ? 'bg-white text-[#050507]' : 'text-white/60 hover:text-white'"
                                class="cursor-pointer rounded-full px-5 py-2.5 text-sm font-semibold transition-colors"
                            >{{ $member['label'] }}</button>
                        @endforeach
                    </div>
                </div>
        @endif

        @if ($swap)
            {{-- Trekt het blok onder de knoppen, zodat zijn bovenmarge niet bovenop
                 de ruimte van de knoppen komt. --}}
            <div class="-mt-10 md:-mt-14" x-show="active === @js($swap['anchor'])" @if ($swap['initial'] !== $swap['anchor']) x-cloak @endif>
        @endif

        <x-dynamic-component
            :component="$componentName"
            :section="$section"
            :content="$content"
        />

        @if ($swap)
            </div>
        @endif
        @if ($swap && $swap['last'])
            </div>
        @endif
    @endforeach
    </div>
</x-layouts.site>
