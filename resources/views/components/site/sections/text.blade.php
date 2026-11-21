@props(['section' => null, 'content' => []])

@php
    $bg      = \Webgoeroe\Core\Filament\Schemas\Sections\SectionBackground::classes($content['background'] ?? null);
    $isDark  = \Webgoeroe\Core\Filament\Schemas\Sections\SectionBackground::isDark($content['background'] ?? null);
    $isFirst = $content['is_first'] ?? false;
@endphp

<x-site.sections.wrapper :content="$content" class="{{ $bg }}">
    @php
        // Tabellen met een kopregel worden een vergelijking en breken uit de smalle kolom.
        $parts = \App\Support\ComparisonTable::split($content['body'] ?? '');
        $proseClass = 'prose max-w-none [&_td]:px-3 [&_td]:align-top [&_th]:px-3 [&_th]:text-left [&_td:first-child]:pl-0 [&_th:first-child]:pl-0 '
            .($isDark ? 'prose-invert prose-p:text-white/70' : '');
    @endphp

    <div class="mx-auto max-w-3xl px-6 pt-16 md:pt-24">
        @if (! empty($content['heading']))
            @if ($isFirst)
                <h1 class="mb-8 text-4xl font-black tracking-tight {{ $isDark ? 'text-white' : 'text-slate-900' }} md:text-5xl">{{ $content['heading'] }}</h1>
            @else
                <h2 class="mb-8 text-3xl font-black tracking-tight {{ $isDark ? 'text-white' : 'text-slate-900' }} md:text-4xl">{{ $content['heading'] }}</h2>
            @endif
        @endif
    </div>

    @foreach ($parts as $part)
        @if ($part['type'] === 'table')
            <div class="mx-auto max-w-6xl px-6 py-4 md:py-6">
                <div class="wg-compare {{ $isDark ? 'wg-compare--dark' : '' }}">
                    <div class="wg-compare__scroll" tabindex="0" role="region" aria-label="Vergelijking">
                        {!! $part['html'] !!}
                    </div>
                </div>
            </div>
        @else
            <div class="mx-auto max-w-3xl px-6 py-2">
                <div class="{{ $proseClass }}">{!! $part['html'] !!}</div>
            </div>
        @endif
    @endforeach

    <div class="pb-14 md:pb-20"></div>
</x-site.sections.wrapper>
