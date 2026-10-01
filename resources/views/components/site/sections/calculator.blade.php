@props(['section' => null, 'content' => []])

@php
    $bg       = \App\Filament\Schemas\Sections\SectionBackground::classes($content['background'] ?? null);
    $missed   = (float) ($content['default_missed'] ?? 5);
    $value    = (float) ($content['default_value'] ?? 800);
    $rate     = (float) ($content['default_rate'] ?? 30);
    $ctaHref  = \App\Support\Url::resolveCtaHref($content, '');
    $inputCls = 'mt-2 w-full rounded-xl border border-white/10 bg-white/[0.05] px-4 py-3 text-lg font-semibold text-white outline-none transition focus:border-cyan-400/50';
@endphp

<x-site.sections.wrapper :content="$content" class="{{ $bg }}">
    <div class="mx-auto max-w-5xl px-6 py-20 md:py-28">
        @if (! empty($content['heading']))
            <div class="mx-auto mb-12 max-w-3xl text-center">
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

        <div
            data-reveal
            x-data="{
                missed: {{ $missed }}, value: {{ $value }}, rate: {{ $rate }},
                get weekly() { return Math.max(0, this.missed) * Math.max(0, this.value) * Math.min(100, Math.max(0, this.rate)) / 100 },
                get monthly() { return this.weekly * 52 / 12 },
                get yearly() { return this.weekly * 52 },
                fmt(n) { return new Intl.NumberFormat('nl-BE', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(n) }
            }"
            class="grid gap-8 rounded-3xl border border-white/[0.08] bg-white/[0.04] p-6 backdrop-blur-sm md:grid-cols-2 md:p-10"
        >
            <div class="space-y-6">
                <label class="block text-sm font-medium text-white/60">Gemiste oproepen per week
                    <input type="number" min="0" inputmode="numeric" x-model.number="missed" class="{{ $inputCls }}">
                </label>
                <label class="block text-sm font-medium text-white/60">Gemiddelde waarde van een opdracht (€)
                    <input type="number" min="0" inputmode="numeric" x-model.number="value" class="{{ $inputCls }}">
                </label>
                <label class="block text-sm font-medium text-white/60">Hoeveel procent van die bellers werd klant? (%)
                    <input type="number" min="0" max="100" inputmode="numeric" x-model.number="rate" class="{{ $inputCls }}">
                </label>
            </div>

            <div class="flex flex-col justify-center rounded-2xl border border-cyan-400/20 bg-gradient-to-br from-cyan-400/10 to-primary-600/10 p-8 text-center">
                <p class="text-sm font-medium text-white/60">Je laat ongeveer liggen per maand</p>
                <p class="mt-2 text-5xl font-black tracking-tight text-white" x-text="fmt(monthly)" aria-live="polite"></p>
                <p class="mt-4 text-sm text-white/50">Per jaar: <span class="font-semibold text-white" x-text="fmt(yearly)"></span></p>

                @if (! empty($content['cta_label']) && $ctaHref !== '')
                    <a href="{{ $ctaHref }}"
                       class="mx-auto mt-8 inline-block cursor-pointer px-7 py-3.5 text-sm font-semibold transition-all"
                       style="background:#fff; color:#000; border-radius:100px;">{{ $content['cta_label'] }}</a>
                @endif
            </div>
        </div>

        @if (! empty($content['disclaimer']))
            <p class="mt-5 text-center text-xs text-white/30">{{ $content['disclaimer'] }}</p>
        @endif
    </div>
</x-site.sections.wrapper>
