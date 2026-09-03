{{--
    "Bekijk website"-oogje in de topbalk van de admin — altijd zichtbaar, op
    elke pagina, tussen de zoekbalk en het accountmenu (zelfde plek als in
    Bailando Latino en El Pablo). Filament's icon-button-component zorgt voor
    de juiste hover/focus-styling in licht én donker thema; de
    `fi-topbar-end`-container geeft zelf de ademruimte t.o.v. de buren.
--}}
{{-- Bewust géén target="_blank": de site opent in het huidige tabblad
     (terug naar de admin = gewoon de terugknop). --}}
<x-filament::icon-button
    icon="heroicon-o-eye"
    tag="a"
    :href="url('/')"
    label="Bekijk website"
    tooltip="Bekijk website"
    color="primary"
    size="lg"
/>
