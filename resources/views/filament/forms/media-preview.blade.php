{{--
    Voorbeeldthumbnail van de gekozen afbeelding, direct onder het MediaPickerField.
    Krijgt de URL expliciet mee ($url) vanuit de belowContent-closure — die leest de
    veldwaarde ($state) rechtstreeks, dus geen $get()/statePath-afhankelijkheid die
    binnen Repeaters (dynamische UUID-paden) breekt.

    Layout-kritische styling staat INLINE: de admin laadt de app-Tailwind niet, dus
    utility-classes zouden hier niet werken (zie media-gallery-picker.blade.php).
--}}
@if (filled($url ?? null))
    <div style="margin-top:0.375rem; display:inline-flex; align-items:center; padding:0.375rem; border:1px solid rgb(228 228 231); border-radius:0.5rem; background:#fff;">
        <img
            src="{{ $url }}"
            alt="Voorbeeld van de geselecteerde afbeelding"
            style="display:block; height:3rem; width:auto; max-width:14rem; object-fit:contain; border-radius:0.25rem;"
            loading="lazy"
        >
    </div>
@endif
