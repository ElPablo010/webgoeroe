{{-- Filament zet na een Livewire-update de focus terug op het actieve tabblad als het
     element met focus verdwenen is (bv. de knop "Veld toevoegen" van een repeater, die
     bij het hertekenen vervangen wordt), en scrollt dat tabblad in beeld. De pagina sprong
     zo naar boven. Een tabblad dat buiten beeld ligt, haalt hier daarom nooit de pagina
     naar zich toe; tabbladen kiezen met muis of toetsenbord gebeurt toch in beeld. --}}
<script>
    (() => {
        const isTab = (element) => element.classList?.contains('fi-tabs-item');
        const isOffscreen = (element) => {
            const rect = element.getBoundingClientRect();

            return rect.bottom < 0 || rect.top > window.innerHeight;
        };

        const focus = HTMLElement.prototype.focus;
        HTMLElement.prototype.focus = function (options) {
            return focus.call(this, isTab(this) ? { ...(options || {}), preventScroll: true } : options);
        };

        const scrollIntoView = Element.prototype.scrollIntoView;
        Element.prototype.scrollIntoView = function (...args) {
            if (isTab(this) && isOffscreen(this)) {
                return;
            }

            return scrollIntoView.apply(this, args);
        };
    })();
</script>
