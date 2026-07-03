@php
    $mediaList = \App\Models\WebsiteMedia::query()->latest()->limit(500)->get();
@endphp

{{--
    Layout-kritische stijlen staan INLINE (style="...") i.p.v. via Tailwind-
    utility-classes. Reden: deze view rendert binnen een Filament-modal, en
    Filament laadt de app-Tailwind (resources/css/app.css) niet — utilities als
    grid-cols-*, aspect-square en max-h-[60vh] zitten niet in Filaments eigen
    CSS-bundle, waardoor de grid instortte tot één kolom met volle-breedte beelden.
    Inline CSS hangt nergens van af. `repeat(auto-fill, minmax(...))` geeft een
    responsieve grid zonder media-queries.
--}}
<div
    x-data="{
        // Two-way binding op de ViewField-state via $entangle — de canonieke
        // Filament-manier. $wire.set() bleek onbetrouwbaar binnen een action-modal,
        // waardoor media_id leeg bleef en de required-validatie 'Kiezen' stil blokkeerde.
        selected: $wire.$entangle(@js($getStatePath())),
        search: '',
        matches(filename) {
            return this.search === '' || filename.toLowerCase().includes(this.search.toLowerCase());
        },
    }"
    style="display: flex; flex-direction: column; gap: 0.75rem;"
>
    <input
        type="text"
        x-model="search"
        placeholder="Zoeken op bestandsnaam..."
        style="width: 100%; border: 1px solid rgb(209 213 219); border-radius: 0.5rem; padding: 0.5rem 0.75rem; font-size: 0.875rem;"
    >

    @if ($mediaList->isEmpty())
        <p style="border: 1px dashed rgb(209 213 219); border-radius: 0.5rem; padding: 2rem; text-align: center; font-size: 0.875rem; color: rgb(107 114 128);">
            Nog geen afbeeldingen in de mediabibliotheek. Klik op "Upload afbeelding" om er een toe te voegen.
        </p>
    @else
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 0.75rem; max-height: 60vh; overflow-y: auto;">
            @foreach ($mediaList as $media)
                <button
                    type="button"
                    x-show="matches(@js(strtolower($media->original_filename ?? '')))"
                    @click="selected = {{ $media->id }}"
                    :style="selected === {{ $media->id }}
                        ? 'outline: 3px solid rgb(79 70 229); outline-offset: -1px;'
                        : 'outline: 1px solid rgb(229 231 235);'"
                    style="display: block; overflow: hidden; border-radius: 0.5rem; background: #fff; text-align: left; cursor: pointer; padding: 0;"
                >
                    <img
                        src="{{ $media->url }}"
                        alt="{{ $media->original_filename }}"
                        style="display: block; width: 100%; aspect-ratio: 1 / 1; object-fit: cover;"
                        loading="lazy"
                    >
                    <span style="display: block; padding: 0.5rem; font-size: 0.75rem; color: rgb(55 65 81); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        {{ $media->original_filename }}
                    </span>
                </button>
            @endforeach
        </div>
    @endif
</div>
