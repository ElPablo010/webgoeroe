@props(['section' => null, 'content' => []])

@php
    $bgKey   = $content['background'] ?? 'primary';
    $bg      = \App\Filament\Schemas\Sections\SectionBackground::classes($bgKey);
    $isDark  = \App\Filament\Schemas\Sections\SectionBackground::isDark($bgKey);
    $isFirst = $content['is_first'] ?? false;
    $ctas    = $content['ctas'] ?? [];
    $note    = $content['note'] ?? '';
@endphp

<x-site.sections.wrapper :content="$content" class="{{ $bg }}">
    <div class="mx-auto max-w-5xl px-6 py-16 md:py-24">

        {{--
            Glass-card container — exact leadexpert CTA-stijl:
            donkere kaart (#0c0c10) met subtiele border en afgeronde hoeken,
            zit op de donkere paginaachtergrond.
        --}}
        <div
            class="relative overflow-hidden rounded-3xl px-8 py-16 text-center md:px-16 md:py-20"
            style="background:#0c0c10; border:1px solid rgba(255,255,255,0.07);"
        >
            {{-- Decoratieve verticale lijn bovenaan (leadexpert-detail) --}}
            <div
                class="absolute left-1/2 top-0 h-20 w-px -translate-x-1/2"
                style="background:linear-gradient(to bottom, transparent, rgba(34,211,238,0.4), transparent);"
            ></div>

            {{-- Subtiele orbs in de kaart --}}
            <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 animate-glow-drift-slow rounded-full blur-[80px]"
                 style="background:rgba(124,58,237,0.10);"></div>
            <div class="pointer-events-none absolute -bottom-24 -left-24 h-64 w-64 animate-glow-drift rounded-full blur-[80px]"
                 style="background:rgba(34,211,238,0.06);"></div>

            {{-- Eyebrow --}}
            @if (! empty($content['eyebrow']))
                <div class="mb-6 inline-flex items-center gap-2 rounded-full px-4 py-1.5"
                     style="border:1px solid rgba(34,211,238,0.2); background:rgba(34,211,238,0.07);">
                    <span class="text-xs font-semibold uppercase tracking-wider" style="color:rgba(34,211,238,0.8);">{{ $content['eyebrow'] }}</span>
                </div>
            @endif

            {{-- Heading --}}
            @if (! empty($content['heading']))
                @if ($isFirst)
                    <h1 class="relative text-4xl font-black leading-tight tracking-tight md:text-5xl lg:text-6xl mb-6">
                        <span style="background:linear-gradient(180deg,#fff 0%,rgba(255,255,255,0.65) 100%); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;">
                            {{ $content['heading'] }}
                        </span>
                    </h1>
                @else
                    <h2 class="relative text-4xl font-black leading-tight tracking-tight md:text-5xl lg:text-6xl">
                        <span style="background:linear-gradient(180deg,#fff 0%,rgba(255,255,255,0.65) 100%); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text;">
                            {{ $content['heading'] }}
                        </span>
                    </h2>
                @endif
            @endif

            {{-- Intro --}}
            @if (! empty($content['intro']))
                <div class="prose prose-invert mx-auto mt-5 max-w-lg prose-p:text-white/50 prose-p:leading-relaxed">
                    {!! $content['intro'] !!}
                </div>
            @endif

            {{-- Knoppen --}}
            @if (! empty($ctas))
                <div class="relative mt-10 flex flex-wrap justify-center gap-3">
                    @foreach ($ctas as $cta)
                        @php $variant = $cta['variant'] ?? 'primary'; @endphp

                        @if ($variant === 'primary')
                            {{-- Exacte leadexpert btn-primary: groot, wit, zwart, pill, hover-glow + lift --}}
                            <a
                                href="{{ \App\Support\Url::resolveCtaHref($cta) }}"
                                class="cursor-pointer inline-flex items-center gap-2 px-8 py-4 text-base font-semibold transition-all"
                                style="background:#fff; color:#000; border-radius:100px; box-shadow:0 0 rgba(255,255,255,0);"
                                onmouseenter="this.style.background='rgba(255,255,255,0.92)'; this.style.boxShadow='0 0 40px rgba(255,255,255,0.2),0 8px 30px rgba(0,0,0,0.4)'; this.style.transform='translateY(-1px)'"
                                onmouseleave="this.style.background='#fff'; this.style.boxShadow='0 0 rgba(255,255,255,0)'; this.style.transform=''"
                            >
                                {{ $cta['label'] ?? '' }}
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>

                        @else
                            {{-- Secundaire knop: leadexpert btn-secondary stijl --}}
                            <a
                                href="{{ \App\Support\Url::resolveCtaHref($cta) }}"
                                class="cursor-pointer inline-flex items-center gap-2 px-8 py-4 text-base font-semibold transition-all"
                                style="background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.1); color:rgba(255,255,255,0.7); border-radius:100px;"
                                onmouseenter="this.style.background='rgba(255,255,255,0.10)'; this.style.borderColor='rgba(255,255,255,0.2)'; this.style.color='#fff'; this.style.transform='translateY(-1px)'"
                                onmouseleave="this.style.background='rgba(255,255,255,0.06)'; this.style.borderColor='rgba(255,255,255,0.1)'; this.style.color='rgba(255,255,255,0.7)'; this.style.transform=''"
                            >{{ $cta['label'] ?? '' }}</a>
                        @endif
                    @endforeach
                </div>
            @endif

            {{-- Note / caption --}}
            @if (! empty($note))
                <p class="relative mt-5 text-sm" style="color:rgba(255,255,255,0.25);">{{ $note }}</p>
            @endif
        </div>
    </div>
</x-site.sections.wrapper>
