@props(['section' => null, 'content' => []])

@php
    $bg       = \Webgoeroe\Core\Filament\Schemas\Sections\SectionBackground::classes($content['background'] ?? null);
    $sectors  = collect($content['sectors'] ?? [])
        ->filter(fn ($s) => filled($s['key'] ?? null) && filled($s['label'] ?? null))
        ->map(fn ($s) => [
            'key'   => (string) $s['key'],
            'label' => (string) $s['label'],
            'close' => (float) ($s['close_rate'] ?? 30),
            'value' => (float) ($s['client_value'] ?? 2000),
        ])
        ->values();
    $first    = $sectors->first();
    $config   = [
        'sectors'  => $sectors,
        'sector'   => $first['key'] ?? '',
        'visitors' => (float) ($content['default_visitors'] ?? 500),
        'leads'    => (float) ($content['default_leads'] ?? 5),
        'close'    => $first['close'] ?? 30,
        'value'    => $first['value'] ?? 2000,
        'target'   => (float) ($content['target_rate'] ?? 3),
    ];
    // Gate: eerst voornaam + e-mail, dan pas het resultaat (aan te passen per blok).
    $requireContact = (bool) ($content['require_contact'] ?? true);
    $config['unlocked'] = ! $requireContact;
    $config['endpoint'] = route('omzetcheck.store');
    $ctaHref  = \Webgoeroe\Core\Support\Url::resolveCtaHref($content, '');
    $numCls   = 'w-20 sm:w-24 rounded-lg border border-white/10 bg-white/[0.05] px-3 py-2 text-right text-lg font-semibold text-white outline-none transition focus:border-cyan-400/50 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none [&::-webkit-outer-spin-button]:appearance-none';
    $inputCls = 'mt-2 block w-full min-w-0 rounded-xl border border-white/10 bg-white/[0.05] px-4 py-3 text-lg font-semibold text-white outline-none transition focus:border-cyan-400/50';
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
                ...@js($config),
                first_name: '', email: '', website: '', sending: false, error: '',
                // Naam en e-mail uit de gate meegeven aan de agenda (voorinvullen).
                ctaUrl(base) {
                    // Een anker op de pagina zelf (#scan) laten we ongemoeid: een query erachter breekt de sprong.
                    if (! this.unlocked || ! this.email || base.startsWith('#')) return base;
                    const p = new URLSearchParams({ first_name: this.first_name, email: this.email });
                    return base + (base.includes('?') ? '&' : '?') + p.toString();
                },
                get sectorLabel() { return this.sectors.find(s => s.key === this.sector)?.label ?? this.sector },
                async submit() {
                    this.error = ''; this.sending = true;
                    const tracking = window.wgTracking ? window.wgTracking.payload() : null;
                    try {
                        const r = await fetch(this.endpoint, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                            body: JSON.stringify({
                                first_name: this.first_name, email: this.email, website: this.website,
                                sector: this.sectorLabel, visitors: this.visitors, leads: this.leads,
                                close: this.close, value: this.value, target: this.target, tracking: tracking
                            })
                        });
                        if (r.ok && ! r.redirected) {
                            this.unlocked = true;
                            if (window.wgTracking) window.wgTracking.fire('Lead', tracking);
                            this.$dispatch('omzetcheck-unlocked', { first_name: this.first_name, email: this.email });
                            return;
                        }
                        const j = await r.json().catch(() => ({}));
                        this.error = Object.values(j.errors ?? {}).flat()[0] ?? 'Er ging iets mis. Probeer het opnieuw.';
                    } catch (e) {
                        this.error = 'Er ging iets mis. Probeer het opnieuw.';
                    } finally { this.sending = false }
                },
                init() {
                    // ?sector=<sleutel> (Meta-ads per sector) kiest meteen die sector.
                    const fromUrl = new URLSearchParams(window.location.search).get('sector');
                    if (fromUrl && this.sectors.some(s => s.key === fromUrl)) this.pick(fromUrl);
                },
                pick(key) {
                    const s = this.sectors.find(s => s.key === key);
                    if (! s) return;
                    this.sector = s.key; this.close = s.close; this.value = s.value;
                },
                get current() { return this.visitors > 0 ? Math.max(0, this.leads) / this.visitors * 100 : 0 },
                get goal() { return this.current >= this.target ? this.current * 2 : this.target },
                get extraLeads() { return Math.max(0, this.visitors) * (this.goal - this.current) / 100 },
                get yearly() { return this.extraLeads * Math.min(100, Math.max(0, this.close)) / 100 * Math.max(0, this.value) * 12 },
                fmt(n) { return new Intl.NumberFormat('nl-BE', { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }).format(n) },
                num(n, d = 0) { return new Intl.NumberFormat('nl-BE', { maximumFractionDigits: d }).format(n) }
            }"
            class="grid gap-8 rounded-3xl border border-white/[0.08] bg-white/[0.04] p-6 md:grid-cols-2 md:p-10"
        >
            <div class="min-w-0 space-y-6">
                @if ($sectors->isNotEmpty())
                    <label class="block min-w-0 text-sm font-medium text-white/60">Wat doe je?
                        <select x-model="sector" @change="pick(sector)" class="{{ $inputCls }} cursor-pointer [&>option]:bg-[#0b0b10]">
                            @foreach ($sectors as $s)
                                <option value="{{ $s['key'] }}">{{ $s['label'] }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif
                <div class="divide-y divide-white/[0.06] rounded-2xl border border-white/[0.08]">
                    <label class="flex min-w-0 items-center justify-between gap-4 px-4 py-3">
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-white/80">Websitebezoekers per maand</span>
                            <span class="block text-xs text-white/40">Zie Google Analytics, of schat het</span>
                        </span>
                        <span class="flex shrink-0 items-center gap-1.5 text-sm text-white/50">
                            <span class="w-3 text-right"></span><input type="number" min="0" inputmode="numeric" x-model.number="visitors" class="{{ $numCls }}"><span class="w-3"></span>
                        </span>
                    </label>
                    <label class="flex min-w-0 items-center justify-between gap-4 px-4 py-3">
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-white/80">Aanvragen per maand</span>
                            <span class="block text-xs text-white/40">Via formulier, mail of telefoon</span>
                        </span>
                        <span class="flex shrink-0 items-center gap-1.5 text-sm text-white/50">
                            <span class="w-3 text-right"></span><input type="number" min="0" inputmode="numeric" x-model.number="leads" class="{{ $numCls }}"><span class="w-3"></span>
                        </span>
                    </label>
                    <label class="flex min-w-0 items-center justify-between gap-4 px-4 py-3">
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-white/80">Wordt klant</span>
                            <span class="block text-xs text-white/40">Van die aanvragen</span>
                        </span>
                        <span class="flex shrink-0 items-center gap-1.5 text-sm text-white/50">
                            <span class="w-3 text-right"></span><input type="number" min="0" max="100" inputmode="numeric" x-model.number="close" class="{{ $numCls }}"><span class="w-3">%</span>
                        </span>
                    </label>
                    <label class="flex min-w-0 items-center justify-between gap-4 px-4 py-3">
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-white/80">Waarde per klant</span>
                            <span class="block text-xs text-white/40">Gemiddelde opdracht</span>
                        </span>
                        <span class="flex shrink-0 items-center gap-1.5 text-sm text-white/50">
                            <span class="w-3 text-right">€</span><input type="number" min="0" inputmode="numeric" x-model.number="value" class="{{ $numCls }}"><span class="w-3"></span>
                        </span>
                    </label>
                </div>
            </div>

            <div class="min-w-0">
            @if ($requireContact)
                <form x-show="! unlocked" @submit.prevent="submit()" class="flex h-full min-w-0 flex-col justify-center rounded-2xl border border-cyan-400/20 bg-gradient-to-br from-cyan-400/10 to-primary-600/10 p-6 sm:p-8">
                    <p class="text-lg font-bold text-white">Je resultaat is klaar</p>
                    <p class="mt-1 text-sm text-white/60">Waar mogen we het naartoe sturen? Je ziet het meteen op het scherm en krijgt het ook per mail.</p>
                    <label class="mt-5 block min-w-0 text-sm font-medium text-white/60">Voornaam
                        <input type="text" x-model="first_name" required maxlength="80" autocomplete="given-name" class="{{ $inputCls }}">
                    </label>
                    <label class="mt-4 block min-w-0 text-sm font-medium text-white/60">E-mail
                        <input type="email" x-model="email" required maxlength="191" autocomplete="email" class="{{ $inputCls }}">
                    </label>
                    <input type="text" x-model="website" tabindex="-1" autocomplete="off" aria-hidden="true" class="absolute -left-[9999px] h-0 w-0 opacity-0">
                    <p x-show="error" x-text="error" class="mt-3 text-sm text-red-400" role="alert"></p>
                    <button type="submit" :disabled="sending"
                            class="mt-6 cursor-pointer px-7 py-3.5 text-sm font-semibold transition-all disabled:cursor-not-allowed disabled:opacity-60"
                            style="background:#fff; color:#000; border-radius:100px;">
                        <span x-show="! sending">Toon mijn resultaat</span><span x-show="sending">Even geduld...</span>
                    </button>
                    <p class="mt-3 text-center text-xs text-white/30">Geen spam. Afmelden kan altijd.</p>
                </form>
            @endif
            <div x-show="unlocked" @if ($requireContact) style="display:none" @endif class="flex h-full min-w-0 flex-col justify-center rounded-2xl border border-cyan-400/20 bg-gradient-to-br from-cyan-400/10 to-primary-600/10 p-6 text-center sm:p-8">
                <p class="text-sm font-medium text-white/60">Met dezelfde bezoekers loop je naar schatting mis</p>
                <p class="mt-2 text-4xl font-black tracking-tight text-white sm:text-5xl" x-text="fmt(yearly)" aria-live="polite"></p>
                <p class="mt-1 text-sm text-white/60">per jaar</p>
                <p class="mt-4 text-sm text-white/50">
                    Dat zijn <span class="font-semibold text-white" x-text="num(extraLeads, 1)"></span> extra aanvragen per maand.<br>
                    Waar ze blijven hangen, ontdek je in een gratis Bottleneck Scan.
                </p>

                @if (! empty($content['cta_label']) && $ctaHref !== '')
                    <a href="{{ $ctaHref }}" :href="ctaUrl(@js($ctaHref))"
                       class="mx-auto mt-8 inline-block cursor-pointer px-7 py-3.5 text-sm font-semibold transition-all"
                       style="background:#fff; color:#000; border-radius:100px;">{{ $content['cta_label'] }}</a>
                @endif
            </div>
            </div>
        </div>

        @if (! empty($content['disclaimer']))
            <p class="mt-5 text-center text-xs text-white/30">{{ $content['disclaimer'] }}</p>
        @endif
    </div>
</x-site.sections.wrapper>
