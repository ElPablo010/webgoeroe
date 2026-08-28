@props(['section' => null, 'content' => []])

@php
    $bg       = \App\Filament\Schemas\Sections\SectionBackground::classes($content['background'] ?? null);
    $isFirst  = $content['is_first'] ?? false;
    $benefits = $content['benefits'] ?? [];
    $height   = $content['height'] ?? '700';
    $provider = $content['provider'] ?? 'calendly';
    $url      = trim($content['calendly_url'] ?? '');

    // De linkerkolom vertelt al wat de scan is — de widget toont enkel de
    // kalender. Voeg de embed-parameters zelf toe zodat een kale Calendly-URL
    // uit de admin ook goed rendert.
    if ($url !== '' && $provider === 'calendly') {
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

    $uid = 'cal-' . ($section?->id ?? uniqid());
@endphp

{{-- Boeking boven de fold: de bezoeker heeft al gekozen — links de context,
     rechts meteen de kalender. Bewust géén extra CTA-knop: de kalender is
     de primaire conversie. --}}
<x-site.sections.wrapper :content="$content" class="{{ $bg }}">
    <div class="mx-auto max-w-6xl px-6 py-14 md:py-20">
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
                    <div class="prose prose-invert mt-5 max-w-lg prose-p:text-white/55">{!! $content['intro'] !!}</div>
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
                @if (! $url)
                    <p class="text-center text-sm text-red-500">Geen URL ingesteld.</p>
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
            </div>

        </div>
    </div>
</x-site.sections.wrapper>
