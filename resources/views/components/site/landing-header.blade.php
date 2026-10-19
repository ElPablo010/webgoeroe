{{-- Header van een landingspagina: enkel het logo, zonder link of menu, zodat
     de bezoeker niet wegklikt. Ligt over de hero, net als de gewone header. --}}
@php
    $header = \Webgoeroe\Core\Support\SiteHeader::current();
@endphp

<div class="absolute inset-x-0 top-0 z-40 flex justify-center px-5 pt-6">
    <span class="flex items-center gap-2.5">
        @if (! empty($header['logo']))
            <img src="{{ $header['logo'] }}" alt="{{ $header['name'] }}" class="h-8 w-auto">
        @else
            <span
                class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-sm font-black text-white"
                style="background:linear-gradient(135deg,#22d3ee,#7c3aed); box-shadow:0 0 16px rgba(124,58,237,0.4);"
            >W</span>
        @endif
        <span class="text-base font-bold text-white">{{ $header['name'] }}</span>
    </span>
</div>
