@props(['section' => null, 'content' => []])

@php
    $bg      = \Webgoeroe\Core\Filament\Schemas\Sections\SectionBackground::classes($content['background'] ?? null);
    $isDark  = \Webgoeroe\Core\Filament\Schemas\Sections\SectionBackground::isDark($content['background'] ?? null);
    $isFirst = $content['is_first'] ?? false;
    $items   = $content['items'] ?? [];
@endphp

<x-site.sections.wrapper :content="$content" class="{{ $bg }}">
    <div class="mx-auto max-w-6xl px-6 py-20 md:py-28">

        @if (! empty($content['heading']) || ! empty($content['eyebrow']))
            <div class="mx-auto mb-14 max-w-4xl text-center">
                @if (! empty($content['eyebrow']))
                    <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-cyan-400/20 bg-cyan-400/[0.07] px-3 py-1">
                        <span class="text-xs font-semibold tracking-wider text-cyan-400">{{ $content['eyebrow'] }}</span>
                    </div>
                @endif
                @if (! empty($content['heading']))
                    @if ($isFirst)
                        <h1 class="text-4xl font-black tracking-tight {{ $isDark ? 'text-white' : 'text-slate-900' }} md:text-5xl mb-8">{{ $content['heading'] }}</h1>
                    @else
                        <h2 class="text-3xl font-black tracking-tight {{ $isDark ? 'text-white' : 'text-slate-900' }} md:text-4xl">{{ $content['heading'] }}</h2>
                    @endif
                @endif
            </div>
        @endif

        {{-- Altijd een slider (swipen, met scroll-snap): één review per scherm op
             een gsm, twee op een tablet, drie op brede schermen. Wat er niet
             naast past, schuif je erbij; de bolletjes tonen de posities. --}}
        <div
            x-data="{
                active: 0,
                positions: 1,
                step() {
                    const card = this.$refs.track.firstElementChild;
                    return card ? card.offsetWidth + parseFloat(getComputedStyle(this.$refs.track).columnGap || 0) : 1;
                },
                measure() {
                    const track = this.$refs.track;
                    const perView = Math.max(1, Math.round((track.clientWidth + parseFloat(getComputedStyle(track).columnGap || 0)) / this.step()));
                    this.positions = Math.max(1, track.children.length - perView + 1);
                    this.update();
                },
                update() {
                    const track = this.$refs.track;
                    const atEnd = track.scrollLeft + track.clientWidth >= track.scrollWidth - 2;
                    this.active = atEnd ? this.positions - 1 : Math.round(track.scrollLeft / this.step());
                },
                go(index) {
                    this.$refs.track.scrollTo({ left: index * this.step(), behavior: 'smooth' });
                },
            }"
            x-init="measure()"
            @resize.window.debounce.100ms="measure()"
        >
        <div
            x-ref="track"
            @scroll.debounce.50ms="update()"
            class="flex snap-x snap-mandatory gap-5 overflow-x-auto pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
        >
            @foreach ($items as $index => $item)
                <div
                    data-reveal-scale
                    style="animation-delay: {{ min($index, 5) * 70 }}ms"
                    class="flex w-full shrink-0 snap-start flex-col sm:w-[calc((100%-1.25rem)/2)] lg:w-[calc((100%-2.5rem)/3)] rounded-2xl border border-white/[0.08] bg-white/[0.04] p-7 backdrop-blur-sm {{ $isDark ? '' : 'border-slate-100 bg-white shadow-sm' }}"
                >

                    {{-- Sterren --}}
                    {{-- Leeg = 5 (oude items); 0 = geen sterren (quote zonder echte score). --}}
                    @php $stars = (int) ($item['rating'] ?? 5); @endphp
                    @if ($stars > 0)
                    <div class="mb-5 flex gap-0.5">
                        @for ($i = 0; $i < $stars; $i++)
                            <svg class="h-4 w-4 text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                            </svg>
                        @endfor
                    </div>
                    @endif

                    {{-- Stat-titel: zet het resultaat in de kijker vóór de quote --}}
                    @if (! empty($item['highlight']))
                        <div class="mb-2 text-lg font-bold leading-snug {{ $isDark ? 'text-white' : 'text-slate-900' }}">
                            {{ $item['highlight'] }}
                        </div>
                    @endif

                    <blockquote class="flex-1 text-sm leading-relaxed {{ $isDark ? 'text-white/60' : 'text-slate-700' }}">
                        "{{ $item['quote'] ?? '' }}"
                    </blockquote>

                    <div class="mt-6 flex items-center gap-3">
                        @if (! empty($item['image']))
                            <img
                                src="{{ $item['image'] }}"
                                alt="{{ $item['name'] ?? '' }}"
                                class="h-10 w-10 rounded-full object-cover"
                                loading="lazy"
                            >
                        @else
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-cyan-400/20 bg-gradient-to-br from-cyan-400/15 to-primary-600/15 text-sm font-bold text-cyan-400">
                                {{ strtoupper(substr($item['name'] ?? 'A', 0, 1)) }}
                            </div>
                        @endif
                        <div>
                            <div class="text-sm font-semibold {{ $isDark ? 'text-white' : 'text-slate-900' }}">{{ $item['name'] ?? '' }}</div>
                            @if (! empty($item['role']))
                                <div class="text-xs {{ $isDark ? 'text-white/40' : 'text-slate-500' }}">{{ $item['role'] }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div x-show="positions > 1" x-cloak class="mt-6 flex items-center justify-center gap-4">
            <button
                type="button"
                @click="go(active - 1)"
                :disabled="active === 0"
                class="flex h-10 w-10 cursor-pointer items-center justify-center rounded-full border transition-colors disabled:cursor-not-allowed disabled:opacity-30 {{ $isDark ? 'border-white/15 text-white hover:bg-white/10' : 'border-slate-300 text-slate-900 hover:bg-slate-100' }}"
                aria-label="Vorige review"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <div class="flex items-center gap-2">
            <template x-for="index in positions" :key="index">
                <button
                    type="button"
                    @click="go(index - 1)"
                    :class="active === index - 1 ? 'w-6 bg-cyan-400' : 'w-2 {{ $isDark ? 'bg-white/20' : 'bg-slate-300' }}'"
                    class="h-2 cursor-pointer rounded-full transition-all"
                    :aria-label="'Toon review ' + index"
                ></button>
            </template>
            </div>
            <button
                type="button"
                @click="go(active + 1)"
                :disabled="active === positions - 1"
                class="flex h-10 w-10 cursor-pointer items-center justify-center rounded-full border transition-colors disabled:cursor-not-allowed disabled:opacity-30 {{ $isDark ? 'border-white/15 text-white hover:bg-white/10' : 'border-slate-300 text-slate-900 hover:bg-slate-100' }}"
                aria-label="Volgende review"
            >
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </button>
        </div>
        </div>
    </div>
</x-site.sections.wrapper>
