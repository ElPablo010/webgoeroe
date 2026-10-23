// Ankerlinks (#sectie-id) landen op de inhoud van de sectie, niet op haar
// bovenrand: de binnenmarge bovenaan (py-20/md:py-28 …) wordt via een negatieve
// scroll-margin overgeslagen, op een kleine adem na. Werkt samen met de
// scroll-padding van <html> voor de vaste menubalk.
const BREATHING_ROOM = 24;

function applyScrollMargins() {
    document.querySelectorAll('section[id]').forEach((section) => {
        const inner = section.firstElementChild;
        const padding = parseFloat(getComputedStyle(section).paddingTop)
            + (inner ? parseFloat(getComputedStyle(inner).paddingTop) : 0);

        section.style.scrollMarginTop = `${-Math.max(0, padding - BREATHING_ROOM)}px`;
    });
}

// Tijdens een lange smooth scroll laden er nog afbeeldingen boven het doel,
// waardoor de sectie verschuift en de scroll te vroeg stopt. Na het scrollen
// dus opnieuw uitlijnen, tot het doel stilstaat (max. een paar keer).
function realignAfterScroll(target, attempts = 3) {
    const controller = new AbortController();
    // Vangnet: een listener die blijft hangen mag een latere scroll van de
    // bezoeker niet terugtrekken.
    setTimeout(() => controller.abort(), 5000);

    window.addEventListener('scrollend', () => {
        controller.abort();
        const padding = parseFloat(getComputedStyle(document.documentElement).scrollPaddingTop) || 0;
        const expected = padding + parseFloat(target.style.scrollMarginTop || 0);
        const atBottom = window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 1;

        if (attempts > 0 && ! atBottom && Math.abs(target.getBoundingClientRect().top - expected) > 2) {
            realignAfterScroll(target, attempts - 1);
            target.scrollIntoView({ block: 'start' });
        }
    }, { signal: controller.signal });
}

export function initAnchors() {
    applyScrollMargins();
    window.addEventListener('resize', applyScrollMargins);

    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href*="#"]');
        if (! link || link.pathname !== location.pathname || link.host !== location.host) {
            return;
        }
        const target = link.hash && document.getElementById(decodeURIComponent(link.hash.slice(1)));
        if (target && target.matches('section[id]')) {
            realignAfterScroll(target);
        }
    });

    // De browser scrolt bij het laden al naar de hash, vóór dit script de
    // marges zette: opnieuw uitlijnen.
    const target = location.hash && document.getElementById(decodeURIComponent(location.hash.slice(1)));
    if (target) {
        target.scrollIntoView({ behavior: 'instant', block: 'start' });
    }
}
