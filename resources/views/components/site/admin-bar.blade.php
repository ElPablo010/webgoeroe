@props(['page' => null])

@php
    /**
     * Bewerkknop van de core (0.21) in de stijl van WebGoeroe: rechtsboven onder
     * de menubalk, violet zoals de vroegere snelkoppeling. Enkel voor wie het
     * admin-panel mag zien; een bezoeker krijgt niets in de HTML.
     */
    $links = \Webgoeroe\Core\Core::adminBar()->links($page);
@endphp

@if ($links !== [])
    <div class="pointer-events-none fixed right-4 top-20 z-50 flex flex-row-reverse gap-2 print:hidden">
        @foreach ($links as $link)
            <a href="{{ $link['url'] }}"
               title="{{ $link['label'] }}"
               aria-label="{{ $link['label'] }}"
               class="pointer-events-auto flex h-10 w-10 items-center justify-center rounded-full bg-violet-600 text-white shadow-lg transition hover:bg-violet-700">
                <x-dynamic-component :component="'lucide-'.$link['icon']" class="h-4 w-4" />
            </a>
        @endforeach
    </div>
@endif
