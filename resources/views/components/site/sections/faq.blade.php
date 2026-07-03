@props(['section' => null, 'content' => []])

@php
    $bg      = \App\Filament\Schemas\Sections\SectionBackground::classes($content['background'] ?? null);
    $isDark  = \App\Filament\Schemas\Sections\SectionBackground::isDark($content['background'] ?? null);
    $isFirst = $content['is_first'] ?? false;
    $items   = $content['items'] ?? [];
@endphp

<x-site.sections.wrapper :content="$content" class="{{ $bg }}">
    <div class="mx-auto max-w-3xl px-6 py-20 md:py-28">

        @if (! empty($content['heading']))
            <div class="mb-12 text-center">
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
                    <div class="prose mt-4 {{ $isDark ? 'prose-invert prose-p:text-white/50' : '' }}">{!! $content['intro'] !!}</div>
                @endif
            </div>
        @endif

        <div class="space-y-3" x-data="{ open: null }">
            @foreach ($items as $index => $item)
                <div
                    data-reveal
                    style="animation-delay: {{ min($index, 5) * 60 }}ms; {{ $isDark ? 'border:1px solid rgba(255,255,255,0.08); background:rgba(255,255,255,0.04);' : 'border:1px solid #e2e8f0; background:#fff;' }}"
                    class="overflow-hidden rounded-xl"
                >
                    <button
                        @click="open = open === {{ $index }} ? null : {{ $index }}"
                        class="flex w-full cursor-pointer items-center justify-between gap-4 px-6 py-4 text-left text-sm font-semibold transition-colors {{ $isDark ? 'text-white/80 hover:text-white' : 'text-slate-900 hover:text-primary-700' }}"
                    >
                        <span>{{ $item['question'] ?? '' }}</span>
                        <svg
                            :class="open === {{ $index }} ? 'rotate-180' : ''"
                            class="h-4 w-4 shrink-0 transition-transform duration-200 {{ $isDark ? 'text-white/30' : 'text-slate-400' }}"
                            :style="open === {{ $index }} ? 'color:#22d3ee' : ''"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                    <div
                        x-show="open === {{ $index }}"
                        x-cloak
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 -translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        class="px-6 pb-5"
                    >
                        <div class="prose text-sm {{ $isDark ? 'prose-invert prose-p:text-white/50' : '' }}">{!! $item['answer'] ?? '' !!}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-site.sections.wrapper>
