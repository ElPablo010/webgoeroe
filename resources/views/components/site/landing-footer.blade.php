{{-- Footer van een landingspagina: enkel de verplichte juridische links, in een
     nieuw tabblad zodat de bezoeker de landingspagina niet verlaat. --}}
@php
    $header = \Webgoeroe\Core\Support\SiteHeader::current();
    $links  = [
        'Privacybeleid' => '/privacybeleid',
        'Cookiebeleid'  => '/cookiebeleid',
    ];
@endphp

<footer class="border-t border-white/[0.06] bg-[#050507]">
    <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-3 px-6 py-8 text-xs text-white/40 sm:flex-row">
        <span>© {{ date('Y') }} {{ $header['name'] }}</span>
        <nav class="flex gap-5">
            @foreach ($links as $label => $href)
                <a href="{{ $href }}" target="_blank" rel="noopener" class="cursor-pointer transition hover:text-white/70">{{ $label }}</a>
            @endforeach
        </nav>
    </div>
</footer>
