@props(['steps' => [], 'spaced' => true])

{{--
    Klantreis: de commerciële keten als stille tijdlijn (bv. Bezoeker → Lead →
    Klant). Bewust géén accentkleur — de kaarten eronder zijn de hoofdact.
    Gedeeld door problem-recognition en cards; $spaced zet de marge onder de
    tijdlijn (uit wanneer er niets meer onder volgt).
--}}
@if (! empty($steps))
    {{-- Desktop: dunne doorlopende lijn met kleine iconen --}}
    <div class="hidden md:block {{ $spaced ? 'mb-16' : '' }}" data-reveal>
        <div class="flex items-start">
            @foreach ($steps as $step)
                <div class="flex w-24 shrink-0 flex-col items-center gap-2.5 text-center">
                    @if (! empty($step['icon']))
                        @php $iconComponent = 'lucide-' . str_replace(['_', ' '], '-', strtolower($step['icon'])); @endphp
                        <x-dynamic-component :component="$iconComponent" class="h-5 w-5 text-white/40" />
                    @endif
                    <span class="text-xs font-medium leading-snug text-white/45">{{ $step['label'] ?? '' }}</span>
                </div>
                @unless ($loop->last)
                    <div class="mt-2.5 h-px flex-1 bg-white/10"></div>
                @endunless
            @endforeach
        </div>
    </div>

    {{-- Mobiel: compacte verticale tijdlijn --}}
    <div class="mx-auto max-w-xs md:hidden {{ $spaced ? 'mb-12' : '' }}" data-reveal>
        @foreach ($steps as $step)
            <div class="flex items-center gap-4">
                @if (! empty($step['icon']))
                    @php $iconComponent = 'lucide-' . str_replace(['_', ' '], '-', strtolower($step['icon'])); @endphp
                    <x-dynamic-component :component="$iconComponent" class="h-5 w-5 shrink-0 text-white/40" />
                @endif
                <span class="text-sm font-medium text-white/50">{{ $step['label'] ?? '' }}</span>
            </div>
            @unless ($loop->last)
                <div class="ml-[9px] h-4 w-px bg-white/10"></div>
            @endunless
        @endforeach
    </div>
@endif
