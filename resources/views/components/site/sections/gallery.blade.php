@props(['section' => null, 'content' => []])

@php
    $bg      = \App\Filament\Schemas\Sections\SectionBackground::classes($content['background'] ?? null);
    $isDark  = \App\Filament\Schemas\Sections\SectionBackground::isDark($content['background'] ?? null);
    $isFirst = $content['is_first'] ?? false;
    $columns = (int) ($content['columns'] ?? 3);
    $colClass = match ($columns) {
        2 => 'sm:grid-cols-2',
        4 => 'sm:grid-cols-2 md:grid-cols-4',
        default => 'sm:grid-cols-2 md:grid-cols-3',
    };
    $items = $content['items'] ?? [];
@endphp

<x-site.sections.wrapper :content="$content" class="{{ $bg }}">
    <div class="mx-auto max-w-6xl px-6 py-20 md:py-28">

        @if (! empty($content['heading']))
            <div class="mx-auto mb-12 max-w-2xl text-center">
                @if (! empty($content['eyebrow']))
                    <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-cyan-400/20 bg-cyan-400/[0.07] px-3 py-1">
                        <span class="text-xs font-semibold tracking-wider text-cyan-400">{{ $content['eyebrow'] }}</span>
                    </div>
                @endif
                @if ($isFirst)
                    <h1 class="text-4xl font-black tracking-tight {{ $isDark ? 'text-white' : 'text-slate-900' }} md:text-5xl mb-8">{{ $content['heading'] }}</h1>
                @else
                    <h2 class="text-3xl font-black tracking-tight {{ $isDark ? 'text-white' : 'text-slate-900' }} md:text-4xl">{{ $content['heading'] }}</h2>
                @endif
                @if (! empty($content['intro']))
                    <div class="prose mx-auto mt-4 {{ $isDark ? 'prose-invert prose-p:text-white/50' : '' }}">{!! $content['intro'] !!}</div>
                @endif
            </div>
        @endif

        <div class="grid grid-cols-2 gap-3 {{ $colClass }}">
            @foreach ($items as $index => $item)
                @if (! empty($item['image']))
                    <picture>
                        <source srcset="{{ $item['image'] }}" type="image/webp">
                        <img
                            src="{{ $item['image'] }}"
                            alt="{{ $item['alt'] ?? '' }}"
                            data-reveal
                            style="animation-delay: {{ min($index, 7) * 50 }}ms"
                            class="aspect-square w-full rounded-xl object-cover {{ $isDark ? 'opacity-80 hover:opacity-100 transition-opacity' : '' }}"
                            loading="lazy"
                        >
                    </picture>
                @endif
            @endforeach
        </div>
    </div>
</x-site.sections.wrapper>
