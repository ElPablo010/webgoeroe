@props(['section' => null, 'content' => []])

@php
    $bg      = \App\Filament\Schemas\Sections\SectionBackground::classes($content['background'] ?? null);
    $isFirst = $content['is_first'] ?? false;
    $stats   = $content['stats'] ?? [];
    $colClass = match (count($stats)) {
        1 => 'md:grid-cols-1 max-w-xs mx-auto',
        2 => 'sm:grid-cols-2',
        3 => 'sm:grid-cols-3',
        default => 'sm:grid-cols-2 lg:grid-cols-4',
    };
@endphp

<x-site.sections.wrapper :content="$content" class="{{ $bg }}">
    <div class="mx-auto max-w-6xl px-6 py-20 md:py-28">

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

        @if (! empty($stats))
            <div class="grid gap-8 {{ $colClass }}">
                @foreach ($stats as $index => $stat)
                    <div data-reveal style="animation-delay: {{ min($index, 5) * 80 }}ms" class="text-center">
                        <div data-count-up class="bg-gradient-to-r from-primary-400 to-cyan-400 bg-clip-text text-5xl font-black tracking-tight text-transparent md:text-6xl">
                            {{ $stat['value'] ?? '' }}
                        </div>
                        <p class="mt-2 text-base font-semibold text-white">{{ $stat['label'] ?? '' }}</p>
                        @if (! empty($stat['sublabel']))
                            <p class="mt-1 text-sm text-white/40">{{ $stat['sublabel'] }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-site.sections.wrapper>
