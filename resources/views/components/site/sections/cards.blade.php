@props(['section' => null, 'content' => []])

@php
    $bg      = \App\Filament\Schemas\Sections\SectionBackground::classes($content['background'] ?? null);
    $isDark  = \App\Filament\Schemas\Sections\SectionBackground::isDark($content['background'] ?? null);
    $isFirst = $content['is_first'] ?? false;
    $columns = (int) ($content['columns'] ?? 3);
    $colClass = match ($columns) {
        2 => 'md:grid-cols-2',
        4 => 'sm:grid-cols-2 xl:grid-cols-4',
        default => 'sm:grid-cols-2 lg:grid-cols-3',
    };
    $cards      = $content['cards'] ?? [];
    $maxVisible = ! empty($content['max_visible']) ? (int) $content['max_visible'] : null;
    $hasMore    = $maxVisible && count($cards) > $maxVisible;
@endphp

<x-site.sections.wrapper :content="$content" class="{{ $bg }}">
    <div
        x-data="{ expanded: false }"
        class="mx-auto max-w-6xl px-6 py-20 md:py-28"
    >
        @if (! empty($content['heading']))
            <div class="mx-auto mb-14 max-w-2xl text-center">
                @if (! empty($content['eyebrow']))
                    <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-cyan-400/20 bg-cyan-400/[0.07] px-3 py-1">
                        <span class="text-xs font-semibold tracking-wider text-cyan-400">{{ $content['eyebrow'] }}</span>
                    </div>
                @endif
                @if ($isFirst)
                    <h1 class="text-4xl font-black tracking-tight text-white md:text-5xl mb-8">{{ $content['heading'] }}</h1>
                @else
                    <h2 class="text-3xl font-black tracking-tight text-white md:text-4xl">{{ $content['heading'] }}</h2>
                @endif
                @if (! empty($content['intro']))
                    <div class="prose prose-invert mx-auto mt-4 prose-p:text-white/50">{!! $content['intro'] !!}</div>
                @endif
            </div>
        @endif

        <div class="grid gap-5 {{ $colClass }}">
            @foreach ($cards as $index => $card)
                @php $isHidden = $hasMore && $index >= $maxVisible; @endphp
                <div
                    @if ($isHidden) x-show="expanded" @endif
                    data-reveal
                    style="animation-delay: {{ min($index, 5) * 70 }}ms"
                    class="group flex flex-col rounded-2xl border border-white/[0.08] bg-white/[0.04] p-7 backdrop-blur-sm transition-all duration-300 hover:border-white/[0.14] hover:bg-white/[0.06] hover:shadow-[0_0_0_1px_rgba(34,211,238,0.1),0_8px_40px_rgba(34,211,238,0.04)]"
                >
                    @if (($card['media_type'] ?? 'icon') === 'image' && ! empty($card['image']))
                        <picture>
                            <source srcset="{{ $card['image'] }}" type="image/webp">
                            <img src="{{ $card['image'] }}" alt="{{ $card['title'] ?? '' }}" class="mb-5 aspect-video w-full rounded-xl object-cover">
                        </picture>
                    @elseif (! empty($card['icon']))
                        @php $iconComponent = 'lucide-' . str_replace(['_', ' '], '-', strtolower($card['icon'])); @endphp
                        <div class="mb-5 inline-flex h-12 w-12 items-center justify-center rounded-xl border border-cyan-400/20 bg-gradient-to-br from-cyan-400/15 to-primary-600/10">
                            <x-dynamic-component
                                :component="$iconComponent"
                                class="h-6 w-6 text-cyan-400"
                            />
                        </div>
                    @endif

                    <h3 class="text-lg font-bold text-white">{{ $card['title'] ?? '' }}</h3>

                    @if (! empty($card['subtitle']))
                        <p class="mt-0.5 text-sm text-cyan-400/70">{{ $card['subtitle'] }}</p>
                    @endif

                    @if (! empty($card['description']))
                        <p class="mt-3 flex-1 text-sm leading-relaxed text-white/50">{{ $card['description'] }}</p>
                    @endif

                    @if (! empty($card['features']))
                        <ul class="mt-4 space-y-2">
                            @foreach ($card['features'] as $feature)
                                <li class="flex items-center gap-2.5 text-sm text-white/60">
                                    <svg class="h-4 w-4 shrink-0 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    {{ $feature }}
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @php $cardHref = \App\Support\Url::resolveCtaHref($card, ''); @endphp
                    @if (! empty($card['cta_label']) && $cardHref !== '')
                        <a
                            href="{{ $cardHref }}"
                            class="mt-6 inline-flex cursor-pointer items-center gap-1.5 text-sm font-semibold text-white/60 transition-colors hover:text-white"
                        >
                            {{ $card['cta_label'] }}
                            <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                    @endif
                </div>
            @endforeach
        </div>

        @if ($hasMore)
            <div class="mt-10 text-center">
                <button
                    @click="expanded = !expanded"
                    x-text="expanded ? 'Minder tonen' : 'Toon meer'"
                    class="cursor-pointer rounded-full border border-white/[0.08] bg-white/[0.04] px-6 py-2.5 text-sm font-semibold text-white/60 transition-all hover:border-white/15 hover:text-white"
                ></button>
            </div>
        @endif
    </div>
</x-site.sections.wrapper>
