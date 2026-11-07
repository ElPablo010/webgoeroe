@props(['section' => null, 'content' => []])

{{-- Lekkencheck (core 0.15.10) in de stijl van deze site. De vragen, het resultaat en
     de logica komen uit de core (livewire/lead-check). --}}
@php
    $bg      = \Webgoeroe\Core\Filament\Schemas\Sections\SectionBackground::classes($content['background'] ?? null);
    $isDark  = \Webgoeroe\Core\Filament\Schemas\Sections\SectionBackground::isDark($content['background'] ?? null);
    $isFirst = $content['is_first'] ?? false;
@endphp

<x-site.sections.wrapper :content="$content" class="{{ $bg }}">
    <div class="mx-auto max-w-3xl px-6 py-20 md:py-28">
        <div class="text-center">
            @if (! empty($content['eyebrow']))
                <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-cyan-400/20 bg-cyan-400/[0.07] px-3 py-1">
                    <span class="text-xs font-semibold tracking-wider text-cyan-400">{{ $content['eyebrow'] }}</span>
                </div>
            @endif
            @if (! empty($content['heading']))
                @if ($isFirst)
                    <h1 class="text-4xl font-black tracking-tight {{ $isDark ? 'text-white' : 'text-slate-900' }} md:text-5xl">{{ $content['heading'] }}</h1>
                @else
                    <h2 class="text-3xl font-black tracking-tight {{ $isDark ? 'text-white' : 'text-slate-900' }} md:text-4xl">{{ $content['heading'] }}</h2>
                @endif
            @endif
            @if (! empty($content['intro']))
                <div class="prose mx-auto mt-5 max-w-2xl {{ $isDark ? 'prose-invert prose-p:text-white/60' : '' }}">{!! $content['intro'] !!}</div>
            @endif
        </div>

        <div class="mt-10 text-left">
            @if ($section?->getKey())
                @livewire('core.lead-check', ['sectionId' => $section->getKey()], key('lead-check-'.$section->getKey()))
            @endif
        </div>
    </div>
</x-site.sections.wrapper>
