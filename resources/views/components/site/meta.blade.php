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
    'graph' => [],
])

@php
    $title = $title ?? \App\Support\Seo::siteName();
    $description = $description ?? \App\Support\Seo::defaultDescription();
    $ogImage = \App\Support\Seo::absoluteUrl($image);
    $favicon = \App\Support\SiteHeader::favicon();
    $faviconType = \App\Support\SiteHeader::faviconType($favicon);
@endphp

@if ($favicon)
    <link rel="icon" href="{{ $favicon }}"@if ($faviconType) type="{{ $faviconType }}"@endif>
    <link rel="apple-touch-icon" href="{{ $favicon }}">
@endif

<title>{{ $title }}</title>
<meta name="description" content="{{ $description }}">
<meta name="robots" content="{{ $robots ?: 'index, follow' }}">
@if ($canonical)
    <link rel="canonical" href="{{ $canonical }}">
@endif

{{-- Open Graph (Facebook, LinkedIn, WhatsApp, …) --}}
<meta property="og:type" content="{{ $type }}">
<meta property="og:site_name" content="{{ \App\Support\Seo::siteName() }}">
<meta property="og:locale" content="{{ \App\Support\Seo::LOCALE }}">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
@if ($canonical)
    <meta property="og:url" content="{{ $canonical }}">
@endif
@if ($ogImage)
    <meta property="og:image" content="{{ $ogImage }}">
    @if ($imageAlt)
        <meta property="og:image:alt" content="{{ $imageAlt }}">
    @endif
    @if ($imageWidth && $imageHeight)
        <meta property="og:image:width" content="{{ $imageWidth }}">
        <meta property="og:image:height" content="{{ $imageHeight }}">
    @endif
@endif

{{-- Twitter / X card --}}
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $title }}">
<meta name="twitter:description" content="{{ $description }}">
@if ($ogImage)
    <meta name="twitter:image" content="{{ $ogImage }}">
@endif

{{-- Structured data (schema.org JSON-LD) — site-breed + pagina-specifiek in één @graph. --}}
@if (! empty($graph))
    <script type="application/ld+json">{!! \App\Support\Seo::jsonLd($graph) !!}</script>
@endif
