@props([
    'title' => null,
    'description' => null,
    'canonical' => null,
    'robots' => 'index, follow',
    'image' => null,
    'imageAlt' => null,
    'imageWidth' => null,
    'imageHeight' => null,
    'type' => 'website',
    'schema' => [],
    'page' => null,
    'editUrl' => null,
])

@php
    // Site-brede structured data (LocalBusiness + WebSite) + pagina-specifieke nodes
    // in één @graph.
    $graph = array_merge(\Webgoeroe\Core\Support\Seo::globalGraph(), $schema ?? []);

    // Landingspagina (Instellingen → Algemeen): geen menu en footer, de bezoeker blijft op de pagina.
    $isLanding = \App\Support\LandingPages::contains($page);
@endphp

<!DOCTYPE html>
{{-- Ankerlinks (#sectie-id) landen net onder de vaste menubalk. De ID staat op
     de sectie zelf; de landingsheader schuift mee, die heeft geen marge nodig. --}}
<html lang="nl" @class(['scroll-pt-24' => ! $isLanding])>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <x-site.meta
        :title="$title"
        :description="$description"
        :canonical="$canonical"
        :robots="$robots"
        :image="$image"
        :image-alt="$imageAlt"
        :image-width="$imageWidth"
        :image-height="$imageHeight"
        :type="$type"
        :graph="$graph"
    />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    {{-- Staat bewust vóór Alpine: de listener op cookie-consent-changed moet
         al klaarstaan wanneer de banner een eerder bewaarde keuze doorgeeft. --}}
    <x-seo-growth::site.analytics />
    <x-site.meta-pixel />
</head>
<body class="min-h-screen bg-[#050507] text-white antialiased">
    @if ($isLanding)
        <x-site.landing-header />
    @else
        <x-site.header />
    @endif

    {{-- Snelkoppeling naar het bewerkscherm: pagina's geven $page mee, blog en
         cases hun eigen editUrl — zo wissel je heen en weer zonder van pagina
         te veranderen (de admin heeft de omgekeerde "Bekijken"-actie). --}}
    @auth
        @if ($page || $editUrl)
            <a
                href="{{ $editUrl ?? route('filament.admin.resources.pages.edit', ['record' => $page, 'tab' => 'sections']) }}"
                class="fixed right-4 top-20 z-50 flex h-10 w-10 items-center justify-center rounded-full bg-violet-600 text-white shadow-lg transition hover:bg-violet-700"
                title="Bewerk deze pagina"
                aria-label="Bewerk pagina"
            >
                <x-lucide-square-pen class="h-4 w-4" />
            </a>
        @endif
    @endauth

    <main>
        {{ $slot }}
    </main>

    @if ($isLanding)
        <x-site.landing-footer />
    @else
        <x-site.footer />
    @endif
    <x-site.cookie-consent />

    {{-- Chat-widget van GoHighLevel (All-in-one chat): gesprekken komen binnen
         in GHL Conversations, naast Facebook en Instagram. Niet op landingspagina's:
         die hebben één doel en de widget zou ervan afleiden of de boeking afdekken. --}}
    @unless ($isLanding)
        <script src="https://widgets.leadconnectorhq.com/loader.js" data-resources-url="https://widgets.leadconnectorhq.com/chat-widget/loader.js" data-widget-id="6a7c4e21b4079d569a93631e" defer></script>
    @endunless
</body>
</html>
