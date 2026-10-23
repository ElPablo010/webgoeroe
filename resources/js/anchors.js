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

export function initAnchors() {
    applyScrollMargins();
    window.addEventListener('resize', applyScrollMargins);

    // De browser scrolt bij het laden al naar de hash, vóór dit script de
    // marges zette: opnieuw uitlijnen.
    const target = location.hash && document.getElementById(decodeURIComponent(location.hash.slice(1)));
    if (target) {
        target.scrollIntoView({ behavior: 'instant', block: 'start' });
    }
}
