@props(['section' => null, 'content' => []])

@php
    $image    = $content['image'] ?? [];
    $ctas     = $content['ctas'] ?? [];
    $hasImage = ! empty($image['src']);
    $compact  = ($content['size'] ?? 'default') === 'compact';
@endphp

<section
    @if (! empty($content['section_id'])) id="{{ $content['section_id'] }}" @endif
    class="relative flex overflow-hidden bg-[#050507] {{ $compact ? 'items-center' : 'min-h-[90vh] items-center' }}"
>
    {{-- Subtiel grid-patroon --}}
    <div
        class="absolute inset-0 opacity-100"
        style="background-image: linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px); background-size: 60px 60px;"
    ></div>

    @if ($hasImage)
        {{-- Achtergrondafbeelding met donker scrim --}}
        <picture>
            <source srcset="{{ $image['src'] }}" type="image/webp">
            <img
                src="{{ $image['src'] }}"
                alt="{{ $image['alt'] ?? '' }}"
                class="absolute inset-0 h-full w-full object-cover opacity-20"
                style="object-position: {{ $image['position'] ?? 'center 50%' }};"
                fetchpriority="high"
            >
        </picture>
        <div class="absolute inset-0 bg-gradient-to-b from-[#050507]/60 via-transparent to-[#050507]"></div>
    @else
        {{-- Cyan orb — rechts boven --}}
        <div class="pointer-events-none absolute -right-48 -top-48 h-[700px] w-[700px] animate-glow-drift rounded-full bg-cyan-400/[0.10] blur-[120px]"></div>
        {{-- Violet orb — links onder --}}
        <div class="pointer-events-none absolute -bottom-48 -left-48 h-[600px] w-[600px] animate-glow-drift-alt rounded-full bg-primary-600/[0.12] blur-[120px]"></div>
        {{-- Subtiel middenglans --}}
        <div class="pointer-events-none absolute inset-0" style="background: radial-gradient(ellipse 80% 50% at 50% 0%, rgba(124,58,237,0.06) 0%, transparent 70%);"></div>
    @endif

    <div class="relative mx-auto w-full max-w-6xl px-6 {{ $compact ? 'pt-28 pb-16 md:pt-32 md:pb-20' : 'py-28 md:py-36' }}">
        <div class="mx-auto max-w-3xl text-center">

            @if (! empty($content['eyebrow']))
                <div class="mb-8 inline-flex animate-hero-enter items-center gap-2 rounded-full border border-cyan-400/20 bg-cyan-400/[0.07] px-4 py-1.5">
                    <span class="h-1.5 w-1.5 rounded-full bg-cyan-400"></span>
                    <span class="text-xs font-semibold tracking-wider text-cyan-400">{{ $content['eyebrow'] }}</span>
                </div>
            @endif

            @if (! empty($content['heading']))
                <h1 class="animate-hero-enter text-5xl font-black leading-[1.05] tracking-[-0.02em] [animation-delay:110ms] md:text-6xl lg:text-7xl">
                    <span class="bg-gradient-to-b from-white to-white/50 bg-clip-text text-transparent">
                        {!! nl2br(e($content['heading'])) !!}
                    </span>
                </h1>
            @endif

            @if (! empty($content['subtitle']))
                <div class="mx-auto mt-6 max-w-xl animate-hero-enter text-lg leading-relaxed text-white/55 [animation-delay:210ms] [&_a]:underline [&_strong]:font-semibold [&_strong]:text-white/90">
                    {!! $content['subtitle'] !!}
                </div>
            @endif

            @if (! empty($ctas))
                <div class="mt-10 flex animate-hero-enter flex-wrap justify-center gap-3 [animation-delay:300ms]">
                    @foreach ($ctas as $cta)
                        @php $variant = $cta['variant'] ?? 'primary'; @endphp

                        @if ($variant === 'primary')
                            {{-- Exacte leadexpert btn-primary: wit, zwart, pill, hover-glow + lift --}}
                            <a
                                href="{{ \App\Support\Url::resolveCtaHref($cta) }}"
                                class="cursor-pointer px-7 py-3.5 text-sm font-semibold transition-all"
                                style="background:#fff; color:#000; border-radius:100px; box-shadow:0 0 rgba(255,255,255,0);"
                                onmouseenter="this.style.background='rgba(255,255,255,0.92)'; this.style.boxShadow='0 0 40px rgba(255,255,255,0.2),0 8px 30px rgba(0,0,0,0.4)'; this.style.transform='translateY(-1px)'"
                                onmouseleave="this.style.background='#fff'; this.style.boxShadow='0 0 rgba(255,255,255,0)'; this.style.transform=''"
                            >{{ $cta['label'] ?? '' }}</a>

                        @elseif ($variant === 'ghost' || $variant === 'secondary')
                            {{-- Exacte leadexpert btn-secondary: donker-transparant, witte rand --}}
                            <a
                                href="{{ \App\Support\Url::resolveCtaHref($cta) }}"
                                class="cursor-pointer px-7 py-3.5 text-sm font-semibold transition-all"
                                style="background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.1); color:rgba(255,255,255,0.7); border-radius:100px;"
                                onmouseenter="this.style.background='rgba(255,255,255,0.1)'; this.style.borderColor='rgba(255,255,255,0.2)'; this.style.color='#fff'; this.style.transform='translateY(-1px)'"
                                onmouseleave="this.style.background='rgba(255,255,255,0.06)'; this.style.borderColor='rgba(255,255,255,0.1)'; this.style.color='rgba(255,255,255,0.7)'; this.style.transform=''"
                            >{{ $cta['label'] ?? '' }}</a>

                        @else
                            <a href="{{ \App\Support\Url::resolveCtaHref($cta) }}" class="cursor-pointer rounded-full bg-primary-600 px-7 py-3.5 text-sm font-semibold text-white transition-all hover:bg-primary-500">{{ $cta['label'] ?? '' }}</a>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    @unless ($compact)
        {{-- Scroll-down indicator: laat zien dat er meer content onder de hero volgt --}}
        <button
            type="button"
            onclick="this.closest('section').nextElementSibling?.scrollIntoView({ behavior: 'smooth' })"
            class="absolute inset-x-0 bottom-8 z-10 flex animate-hero-enter cursor-pointer flex-col items-center gap-2 text-white/40 transition-colors [animation-delay:500ms] hover:text-white/70"
            aria-label="Scroll naar beneden"
        >
            <span class="flex h-9 w-6 items-start justify-center rounded-full border-2 border-current p-1.5">
                <span class="h-1.5 w-1.5 animate-scroll-dot rounded-full bg-current"></span>
            </span>
        </button>
    @endunless
</section>
