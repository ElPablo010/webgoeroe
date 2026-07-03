@props(['section' => null, 'content' => []])

@php
    $bg      = \App\Filament\Schemas\Sections\SectionBackground::classes($content['background'] ?? null);
    $isDark  = \App\Filament\Schemas\Sections\SectionBackground::isDark($content['background'] ?? null);
    $isFirst = $content['is_first'] ?? false;
    $items   = $content['items'] ?? [];
@endphp

<x-site.sections.wrapper :content="$content" class="{{ $bg }}">
    <div class="mx-auto max-w-6xl px-6 py-20 md:py-28">

        @if (! empty($content['heading']) || ! empty($content['eyebrow']))
            <div class="mx-auto mb-14 max-w-2xl text-center">
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

        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($items as $index => $item)
                <div
                    data-reveal-scale
                    style="animation-delay: {{ min($index, 5) * 70 }}ms"
                    class="flex flex-col rounded-2xl border border-white/[0.08] bg-white/[0.04] p-7 backdrop-blur-sm {{ $isDark ? '' : 'border-slate-100 bg-white shadow-sm' }}"
                >

                    {{-- Sterren --}}
                    @php $stars = (int) ($item['rating'] ?? 5); @endphp
                    <div class="mb-5 flex gap-0.5">
                        @for ($i = 0; $i < $stars; $i++)
                            <svg class="h-4 w-4 text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                            </svg>
                        @endfor
                    </div>

                    <blockquote class="flex-1 text-sm leading-relaxed {{ $isDark ? 'text-white/60' : 'text-slate-700' }}">
                        "{{ $item['quote'] ?? '' }}"
                    </blockquote>

                    <div class="mt-6 flex items-center gap-3">
                        @if (! empty($item['avatar']))
                            <img
                                src="{{ $item['avatar'] }}"
                                alt="{{ $item['author'] ?? '' }}"
                                class="h-10 w-10 rounded-full object-cover"
                                loading="lazy"
                            >
                        @else
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-cyan-400/20 bg-gradient-to-br from-cyan-400/15 to-primary-600/15 text-sm font-bold text-cyan-400">
                                {{ strtoupper(substr($item['author'] ?? 'A', 0, 1)) }}
                            </div>
                        @endif
                        <div>
                            <div class="text-sm font-semibold {{ $isDark ? 'text-white' : 'text-slate-900' }}">{{ $item['author'] ?? '' }}</div>
                            @if (! empty($item['company']))
                                <div class="text-xs {{ $isDark ? 'text-white/40' : 'text-slate-500' }}">{{ $item['company'] }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-site.sections.wrapper>
