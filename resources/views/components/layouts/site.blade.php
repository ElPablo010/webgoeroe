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
])

@php
    // Site-brede structured data (LocalBusiness + WebSite) + pagina-specifieke nodes
    // in één @graph.
    $graph = array_merge(\App\Support\Seo::globalGraph(), $schema ?? []);
@endphp

<!DOCTYPE html>
<html lang="nl">
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
</head>
<body class="min-h-screen bg-[#050507] text-white antialiased">
    <x-site.header />

    @auth
        @if ($page)
            <a
                href="{{ route('filament.admin.resources.pages.edit', ['record' => $page, 'tab' => 'sections']) }}"
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

    <x-site.footer />
    <x-site.cookie-consent />
</body>
</html>
