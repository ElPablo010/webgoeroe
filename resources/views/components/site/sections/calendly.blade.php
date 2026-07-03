@props(['section' => null, 'content' => []])

@php
    $bg      = \App\Filament\Schemas\Sections\SectionBackground::classes($content['background'] ?? null);
    $isDark  = \App\Filament\Schemas\Sections\SectionBackground::isDark($content['background'] ?? null);
    $isFirst = $content['is_first'] ?? false;
    $height  = $content['height'] ?? '700';
    $provider = $content['provider'] ?? 'calendly';
    $url      = trim($content['calendly_url'] ?? '');

    // Cal.com: haal de calLink uit de URL (alles na cal.com/)
    $calLink = $provider === 'calcom' && $url
        ? ltrim(parse_url($url, PHP_URL_PATH), '/')
        : null;

    $uid = 'cal-' . ($section?->id ?? uniqid());
@endphp

<x-site.sections.wrapper :content="$content" class="{{ $bg }}">
    <div class="mx-auto max-w-5xl px-6 py-20 md:py-28">

        @if (! empty($content['eyebrow']) || ! empty($content['heading']) || ! empty($content['intro']))
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
                        {!! $content['intro'] !!}
                    </div>
                @endif
            </div>
        @endif

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
            {{-- Calendly inline embed --}}
            <div
                class="calendly-inline-widget w-full overflow-hidden rounded-2xl"
                data-url="{{ $url }}"
                style="min-width:320px; height:{{ $height }}px;"
            ></div>
            <script src="https://assets.calendly.com/assets/external/widget.js" async></script>
        @endif

    </div>
</x-site.sections.wrapper>
