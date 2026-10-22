@props(['section' => null, 'content' => []])

@php
    $bg       = \Webgoeroe\Core\Filament\Schemas\Sections\SectionBackground::classes($content['background'] ?? null);
    $isDark   = \Webgoeroe\Core\Filament\Schemas\Sections\SectionBackground::isDark($content['background'] ?? null);
    $isFirst  = $content['is_first'] ?? false;
    $isHero   = ($content['layout'] ?? 'section') === 'hero';
    $benefits = $content['benefits'] ?? [];
    $height   = $content['height'] ?? '700';
    $provider = $content['provider'] ?? 'calendly';
    $url      = trim($content['url'] ?? '');
    $privacy  = trim((string) ($content['privacy_note'] ?? ''));
    // Vinkjes (✓) in de intro in de accentkleur: een vinkje = voordeel, zoals op de rest van de site.
    $intro    = str_replace('✓', '<span class="'.($isDark ? 'text-cyan-400' : 'text-cyan-600').'">✓</span>', (string) ($content['intro'] ?? ''));

    // Hero: de linkerkolom vertelt al wat de afspraak is — de widget toont
    // enkel de kalender. Voeg de embed-parameters zelf toe zodat een kale
    // Calendly-URL uit de admin ook goed rendert.
    if ($isHero && $url !== '' && $provider === 'calendly') {
        foreach ([
            'hide_event_type_details' => '1',
            'hide_gdpr_banner' => '1',
            'primary_color' => '7c3aed',
        ] as $param => $value) {
            if (! str_contains($url, $param.'=')) {
                $url .= (str_contains($url, '?') ? '&' : '?').$param.'='.$value;
            }
        }
    }

    // Cal.com: haal de calLink uit de URL (alles na cal.com/)
    $calLink = $provider === 'calcom' && $url
        ? ltrim(parse_url($url, PHP_URL_PATH), '/')
        : null;

    $ghlDisplay = $provider === 'ghl' ? ($content['ghl_display'] ?? 'iframe') : null;
    $ghlPopup = $ghlDisplay === 'popup';
    $ghlOwn = $ghlDisplay === 'own';
    $popupLabel = trim((string) ($content['popup_button_label'] ?? '')) ?: 'Kies een moment';

    $uid = 'cal-' . ($section?->id ?? uniqid());
@endphp

{{-- Twee opmaken:
     - hero:    boeking boven de fold — links de context, rechts meteen de
                kalender. Bewust géén extra CTA-knop: de kalender is de
                primaire conversie.
     - section: gecentreerde kop met de kalender eronder. --}}
<x-site.sections.wrapper :content="$content" class="{{ $bg }}">
    <div class="{{ $isHero ? 'mx-auto max-w-6xl px-6 py-14 md:py-20' : 'mx-auto max-w-5xl px-6 py-20 md:py-28' }}">

        @if ($isHero)
            <div class="grid items-start gap-10 lg:grid-cols-2 lg:gap-14">

                <div class="lg:pt-6">
                    @if (! empty($content['badge']))
                        <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-cyan-400/20 bg-cyan-400/[0.07] px-4 py-1.5">
                            <span class="h-1.5 w-1.5 rounded-full bg-cyan-400"></span>
                            <span class="text-xs font-semibold uppercase tracking-wider text-cyan-400">{{ $content['badge'] }}</span>
                        </div>
                    @endif

                    @if (! empty($content['heading']))
                        @if ($isFirst)
                            <h1 class="text-4xl font-black leading-tight tracking-tight text-white md:text-5xl">{{ $content['heading'] }}</h1>
                        @else
                            <h2 class="text-3xl font-black tracking-tight text-white md:text-4xl">{{ $content['heading'] }}</h2>
                        @endif
                    @endif

                    @if (! empty($content['intro']))
                        <div class="prose prose-invert mt-5 max-w-lg prose-p:text-white/55">{!! $intro !!}</div>
                    @endif

                    @if (! empty($benefits))
                        <ul class="mt-8 space-y-3.5">
                            @foreach ($benefits as $benefit)
                                <li class="flex items-center gap-3 text-white/70">
                                    <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full border border-cyan-400/20 bg-cyan-400/[0.08]">
                                        <svg class="h-3.5 w-3.5 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </span>
                                    <span class="text-sm font-medium">{{ $benefit }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div>
        @elseif (! empty($content['eyebrow']) || ! empty($content['heading']) || ! empty($content['intro']))
            <div class="mb-10 text-center">
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
                @if (! empty($content['intro']))
                    <div class="prose mx-auto mt-4 max-w-2xl {{ $isDark ? 'prose-invert prose-p:text-white/50' : '' }}">
                        {!! $intro !!}
                    </div>
                @endif
            </div>
        @endif

                    @if (! $url)
                        <p class="text-center text-sm text-red-500">Geen URL ingesteld.</p>
                    @elseif ($provider === 'ghl')
                        {{-- GoHighLevel-agenda (iframe). Naam en e-mail uit de omzetcheck vullen het
                             formulier voor: via ?first_name=…&email=… op de pagina-URL, of (zelfde
                             pagina) via het event omzetcheck-unlocked, zonder te herladen.
                             Bewust zonder GHL's form_embed.js: dat script verbergt de iframe en laat
                             hem lui laden, waardoor hij onderaan een pagina nooit laadde (eeuwig
                             "Kalender laden…"). Een gewone iframe met vaste hoogte laadt altijd. --}}
                        @php
                            $ghlParams = array_filter([
                                'first_name' => mb_substr((string) request()->query('first_name', ''), 0, 80),
                                'email' => filter_var(request()->query('email'), FILTER_VALIDATE_EMAIL) ?: null,
                            ]);
                            $ghlSrc = $url.($ghlParams ? (str_contains($url, '?') ? '&' : '?').http_build_query($ghlParams) : '');
                        @endphp
                        <link rel="preconnect" href="https://api.leadconnectorhq.com">
                        @if ($ghlOwn)
                            {{-- Eigen kalender: geen iframe, de site toont zelf de vrije momenten (BookingController). --}}
                            <x-site.sections.booking-own :calendar-id="\App\Http\Controllers\BookingController::calendarId($content)" :section-id="$section?->id" :fields="\App\Support\BookingForm::fields($content)" :prefill="$ghlParams" />
                        @elseif ($ghlPopup)
                            {{-- Pop-up: de iframe laadt pas bij het openen en heeft zijn eigen scroll
                                 binnen het venster, dus de pagina zelf blijft rustig. Andere knoppen op
                                 de pagina kunnen hem openen met window.dispatchEvent(new Event('open-booking')). --}}
                            <div x-data="{
                                    open: false,
                                    src: @js($ghlSrc),
                                    loaded: false,
                                    prefill(d) {
                                        const u = new URL(this.src, window.location.href);
                                        if (d?.first_name) u.searchParams.set('first_name', d.first_name);
                                        if (d?.email) u.searchParams.set('email', d.email);
                                        this.src = u.toString();
                                    },
                                    show() { this.open = true; document.body.classList.add('overflow-hidden'); },
                                    close() { this.open = false; document.body.classList.remove('overflow-hidden'); }
                                }"
                                @omzetcheck-unlocked.window="prefill($event.detail)"
                                @open-booking.window="show()"
                                @keydown.escape.window="open && close()"
                                class="text-center">
                                <button type="button" @click="show()"
                                        class="inline-flex cursor-pointer items-center justify-center px-8 py-4 text-base font-semibold transition-all hover:-translate-y-px focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-300 bg-violet-600 text-white hover:bg-violet-500 hover:shadow-lg"
                                        style="border-radius:100px;">
                                    {{ $popupLabel }}
                                </button>

                                <template x-teleport="body">
                                    <div x-show="open" x-cloak x-transition.opacity
                                         class="fixed inset-0 z-[100] flex items-center justify-center bg-slate-950/80 p-3 sm:p-6"
                                         role="dialog" aria-modal="true" aria-label="Kies een moment voor je gesprek"
                                         @click.self="close()">
                                        <div class="relative flex max-h-full w-full max-w-3xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                                            <button type="button" @click="close()" aria-label="Sluiten"
                                                    class="absolute right-3 top-3 z-10 inline-flex cursor-pointer h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-slate-600 hover:bg-slate-200">
                                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                            <div class="relative min-h-[70vh] flex-1">
                                                <div x-show="! loaded" class="absolute inset-0 flex flex-col items-center justify-center gap-3">
                                                    <svg class="h-6 w-6 animate-spin text-slate-300" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                                                    </svg>
                                                    <span class="text-sm font-medium text-slate-400">Kalender laden…</span>
                                                </div>
                                                <template x-if="open">
                                                    <iframe :src="src" @load="loaded = true" title="Kies een moment voor je gesprek"
                                                            class="relative h-[85vh] w-full border-0"></iframe>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        @else
                        <div x-data="{
                                src: @js($ghlSrc),
                                prefill(d) {
                                    const u = new URL(this.src, window.location.href);
                                    if (d?.first_name) u.searchParams.set('first_name', d.first_name);
                                    if (d?.email) u.searchParams.set('email', d.email);
                                    this.src = u.toString();
                                },
                                // Meldt de agenda dat de boeking gelukt is, dan staat de bevestiging bovenaan
                                // de iframe en scrollen we de pagina daarheen (anders zit ze achter de header).
                                onMessage(e) {
                                    if (! /(^|\.)(leadconnectorhq\.com|msgsndr\.com)$/.test(new URL(e.origin).hostname)) return;
                                    const text = typeof e.data === 'string' ? e.data : JSON.stringify(e.data ?? '');
                                    if (/book|schedul|confirm|success/i.test(text) && this.$el.getBoundingClientRect().top < 110) {
                                        this.$el.scrollIntoView({ behavior: 'smooth', block: 'start' });
                                    }
                                }
                            }"
                            @omzetcheck-unlocked.window="prefill($event.detail)"
                            @message.window="onMessage($event)"
                            class="relative overflow-hidden rounded-2xl bg-white px-3 sm:px-8" style="min-height:{{ $height }}px; scroll-margin-top:120px;">
                            <div class="absolute inset-0 flex flex-col items-center justify-center gap-3">
                                <svg class="h-6 w-6 animate-spin text-slate-300" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                                </svg>
                                <span class="text-sm font-medium text-slate-400">Kalender laden…</span>
                            </div>
                            <iframe src="{{ $ghlSrc }}" :src="src" title="Kies een moment voor je gesprek" class="relative w-full border-0"
                                    style="min-width:320px; height:{{ $height }}px;"></iframe>
                        </div>
                        @endif
                    @elseif ($provider === 'calcom' && $calLink)
                        {{-- Cal.com inline embed --}}
                        <div id="{{ $uid }}" class="w-full overflow-hidden rounded-2xl" style="height:{{ $height }}px;"></div>
                        <script type="text/javascript">
                            (function (C, A, L) {
                                let p = function (a, ar) { a.q.push(ar); };
                                let d = C.document;
                                C.Cal = C.Cal || function () {
                                    let cal = C.Cal; let ar = arguments;
                                    if (!cal.loaded) { cal.ns = {}; cal.q = cal.q || []; d.head.appendChild(d.createElement("script")).src = A; cal.loaded = true; }
                                    if (ar[0] === L) { const api = function () { p(api, arguments); }; const namespace = ar[1]; api.q = api.q || []; typeof namespace === "string" ? (cal.ns[namespace] = api) && p(api, ar) : p(cal, ar); return; }
                                    p(cal, ar);
                                };
                            })(window, "https://app.cal.com/embed/embed.js", "init");
                            Cal("init", { origin: "https://cal.com" });
                            Cal("inline", {
                                elementOrSelector: "#{{ $uid }}",
                                calLink: "{{ $calLink }}",
                                config: { layout: "month_view" }
                            });
                        </script>
                    @else
                        {{-- Calendly inline embed in een witte skeleton-kaart: de kaart
                             (met afgeronde hoeken) staat er meteen, de laadindicator
                             zit achter de iframe en verdwijnt zodra Calendly schildert.
                             Preconnect scheelt DNS/TLS-tijd op de trage embed. --}}
                        <link rel="preconnect" href="https://assets.calendly.com" crossorigin>
                        <link rel="preconnect" href="https://calendly.com">
                        <div class="relative overflow-hidden rounded-2xl bg-white" style="min-height:{{ $height }}px;">
                            <div class="absolute inset-0 flex flex-col items-center justify-center gap-3">
                                <svg class="h-6 w-6 animate-spin text-slate-300" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                                </svg>
                                <span class="text-sm font-medium text-slate-400">Kalender laden…</span>
                            </div>
                            <div
                                class="calendly-inline-widget relative w-full"
                                data-url="{{ $url }}"
                                style="min-width:320px; height:{{ $height }}px;"
                            ></div>
                        </div>
                        <script src="https://assets.calendly.com/assets/external/widget.js" async></script>
                    @endif

                    @if ($privacy !== '')
                        <p class="mt-4 text-center text-xs {{ $isHero || $isDark ? 'text-white/40' : 'text-slate-500' }}">{{ $privacy }}</p>
                    @endif

        @if ($isHero)
                </div>

            </div>
        @endif

    </div>
</x-site.sections.wrapper>
