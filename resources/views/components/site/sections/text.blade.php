@props(['section' => null, 'content' => []])

@php
    $bg      = \App\Filament\Schemas\Sections\SectionBackground::classes($content['background'] ?? null);
    $isDark  = \App\Filament\Schemas\Sections\SectionBackground::isDark($content['background'] ?? null);
    $isFirst = $content['is_first'] ?? false;
@endphp

<x-site.sections.wrapper :content="$content" class="{{ $bg }}">
    <div class="mx-auto max-w-3xl px-6 py-16 md:py-24">

        @if (! empty($content['heading']))
            @if ($isFirst)
                <h1 class="mb-8 text-4xl font-black tracking-tight {{ $isDark ? 'text-white' : 'text-slate-900' }} md:text-5xl">{{ $content['heading'] }}</h1>
            @else
                <h2 class="mb-8 text-3xl font-black tracking-tight {{ $isDark ? 'text-white' : 'text-slate-900' }} md:text-4xl">{{ $content['heading'] }}</h2>
            @endif
        @endif

        <div class="prose max-w-none {{ $isDark ? 'prose-invert prose-p:text-white/70' : '' }}">
            {!! $content['body'] ?? '' !!}
        </div>
    </div>
</x-site.sections.wrapper>
