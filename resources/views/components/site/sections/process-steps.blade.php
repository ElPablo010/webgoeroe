@props(['section' => null, 'content' => []])

@php
    $bg    = \App\Filament\Schemas\Sections\SectionBackground::classes($content['background'] ?? null);
    $steps = $content['steps'] ?? [];
@endphp

<x-site.sections.wrapper :content="$content" class="{{ $bg }}">
    <div class="mx-auto max-w-6xl px-6 py-20 md:py-28">
        @if (! empty($content['heading']))
            <div class="mx-auto mb-14 max-w-4xl text-center">
                @if (! empty($content['eyebrow']))
                    <div class="mb-4 inline-flex items-center gap-2 rounded-full border border-cyan-400/20 bg-cyan-400/[0.07] px-3 py-1">
                        <span class="text-xs font-semibold tracking-wider text-cyan-400">{{ $content['eyebrow'] }}</span>
                    </div>
                @endif
                <h2 class="text-3xl font-black tracking-tight text-white md:text-4xl">{{ $content['heading'] }}</h2>
                @if (! empty($content['intro']))
                    <div class="prose prose-invert mx-auto mt-4 prose-p:text-white/50">{!! $content['intro'] !!}</div>
                @endif
            </div>
        @endif

        {{-- Stappen: editorial nummers met doorlopende verbindingslijn --}}
        <div class="grid gap-12 md:grid-cols-3 md:gap-10">
            @foreach ($steps as $index => $step)
                <div data-reveal style="animation-delay: {{ min($index, 5) * 90 }}ms">
                    <div class="flex items-center gap-4">
                        <span class="bg-gradient-to-b from-cyan-400 to-primary-600 bg-clip-text text-5xl font-black leading-none text-transparent">
                            {{ sprintf('%02d', $index + 1) }}
                        </span>
                        <div class="h-px flex-1 bg-gradient-to-r from-cyan-400/30 to-transparent"></div>
                    </div>

                    <h3 class="mt-5 text-lg font-bold text-white">{{ $step['title'] ?? '' }}</h3>

                    @if (! empty($step['description']))
                        <p class="mt-3 text-sm leading-relaxed text-white/50">{{ $step['description'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>

        @if (! empty($content['closing']))
            <div class="mx-auto mt-16 max-w-4xl text-center md:mt-20" data-reveal>
                <div class="text-xl leading-snug text-white/50 md:text-2xl [&_strong]:font-bold [&_strong]:text-white">
                    {!! $content['closing'] !!}
                </div>

                @php $ctaHref = \App\Support\Url::resolveCtaHref($content, ''); @endphp
                @if (! empty($content['cta_label']) && $ctaHref !== '')
                    {{-- Zelfde primaire buttonstijl als de hero-CTA --}}
                    <a
                        href="{{ $ctaHref }}"
                        class="mt-8 inline-block cursor-pointer px-7 py-3.5 text-sm font-semibold transition-all"
                        style="background:#fff; color:#000; border-radius:100px; box-shadow:0 0 rgba(255,255,255,0);"
                        onmouseenter="this.style.background='rgba(255,255,255,0.92)'; this.style.boxShadow='0 0 40px rgba(255,255,255,0.2),0 8px 30px rgba(0,0,0,0.4)'; this.style.transform='translateY(-1px)'"
                        onmouseleave="this.style.background='#fff'; this.style.boxShadow='0 0 rgba(255,255,255,0)'; this.style.transform=''"
                    >{{ $content['cta_label'] }}</a>
                @endif
            </div>
        @endif
    </div>
</x-site.sections.wrapper>
