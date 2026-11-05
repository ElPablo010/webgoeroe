@props(['cta' => null])

{{--
    Afsluitende CTA-banner onder blogartikels en cases (CtaBanner uit de core;
    inhoud via Instellingen → Blog en Instellingen → Cases). Ontwerp van
    De WebGoeroe: primaire knop wit, secundaire knop met rand.
--}}
@if ($cta)
<section class="bg-[#050507] pb-16 md:pb-24">
    <div class="mx-auto max-w-5xl px-6">
        <div
            class="relative overflow-hidden rounded-3xl px-8 py-16 text-center md:px-16 md:py-20"
            style="background:#0c0c10; border:1px solid rgba(255,255,255,0.07);"
        >
            <div class="absolute left-1/2 top-0 h-20 w-px -translate-x-1/2" style="background:linear-gradient(to bottom, transparent, rgba(34,211,238,0.4), transparent);"></div>
            <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full blur-[80px]" style="background:rgba(124,58,237,0.10);"></div>
            <div class="pointer-events-none absolute -bottom-24 -left-24 h-64 w-64 rounded-full blur-[80px]" style="background:rgba(34,211,238,0.06);"></div>

            <h2 class="relative text-3xl font-black leading-tight tracking-tight text-white md:text-4xl lg:text-5xl">
                {{ $cta['title'] }}
            </h2>
            @if (! empty($cta['body']))
                <p class="relative mx-auto mt-5 max-w-lg leading-relaxed text-white/50">
                    {{ $cta['body'] }}
                </p>
            @endif
            @if (! empty($cta['buttons']))
                <div class="relative mt-10 flex flex-wrap justify-center gap-4">
                    @foreach ($cta['buttons'] as $button)
                        <a
                            href="{{ $button['href'] }}"
                            @class([
                                'cursor-pointer inline-flex items-center gap-2 rounded-full px-8 py-4 text-base font-semibold transition-all',
                                'bg-white text-black hover:bg-white/90' => $button['variant'] === 'primary',
                                'border border-white/20 text-white hover:border-white/40 hover:bg-white/[0.06]' => $button['variant'] !== 'primary',
                            ])
                        >
                            {{ $button['label'] }}
                            @if ($button['variant'] === 'primary')
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                </svg>
                            @endif
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</section>
@endif
