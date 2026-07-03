// Scroll-reveal: elementen met [data-reveal] of [data-reveal-scale] krijgen
// .is-visible zodra ze de viewport in scrollen (zie de reveal-up / reveal-scale
// keyframes in resources/css/app.css). Eén observer voor de hele pagina, incl.
// elementen die pas later toegevoegd worden (bv. door een "Toon meer"-toggle)
// via een MutationObserver.
//
// [data-count-up] tekst (bv. "150+", "€1.2M", "98%") telt op van 0 naar de
// eindwaarde zodra 'ie zichtbaar wordt — alleen voor herkenbare, eenvoudige
// getalnotaties; bij twijfel toont het gewoon meteen de eindwaarde.

function observeReveal(observer) {
    document.querySelectorAll('[data-reveal]:not(.is-visible), [data-reveal-scale]:not(.is-visible)')
        .forEach((el) => observer.observe(el));
}

function animateCountUp(el) {
    const match = el.textContent.trim().match(/^(\D*)(\d+(?:\.\d+)?)(\D*)$/);
    if (! match) return;

    const [, prefix, numStr, suffix] = match;
    const target = parseFloat(numStr);
    const decimals = numStr.includes('.') ? numStr.split('.')[1].length : 0;
    const duration = 1200;
    const start = performance.now();

    function tick(now) {
        const progress = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3); // ease-out-cubic
        const value = (target * eased).toFixed(decimals);
        el.textContent = `${prefix}${value}${suffix}`;
        if (progress < 1) requestAnimationFrame(tick);
    }

    requestAnimationFrame(tick);
}

function reveal(el) {
    if (el.classList.contains('is-visible')) return;
    el.classList.add('is-visible');
    if (el.hasAttribute('data-count-up')) animateCountUp(el);
}

export function initReveal() {
    if (! ('IntersectionObserver' in window)) {
        document.querySelectorAll('[data-reveal], [data-reveal-scale]').forEach(reveal);
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) reveal(entry.target);
            // Bewust NIET unobserve()'n: als de browser een snelle scroll-pass
            // ooit mist, blijft het element in observatie en kan het bij een
            // volgende intersectie alsnog getoond worden. reveal() zelf is
            // idempotent, dus herhaaldelijk triggeren is onschadelijk.
        });
    }, { rootMargin: '0px 0px -10% 0px', threshold: 0 });

    observeReveal(observer);

    new MutationObserver(() => observeReveal(observer)).observe(document.body, { childList: true, subtree: true });

    // Vangnet: wat de observer ook mist (snelle scroll, browser-quirk, tab die
    // nooit focus krijgt, …) — na 2s toont dit alsnog alles wat nog verstopt
    // zit. Content mag nooit permanent onzichtbaar blijven door een animatie.
    window.setTimeout(() => {
        document.querySelectorAll('[data-reveal]:not(.is-visible), [data-reveal-scale]:not(.is-visible)').forEach(reveal);
    }, 2000);
}

// Leesvoortgangsbalk voor blogartikelen: vult van 0 naar 100% terwijl je door
// het element met [data-reading-progress-target] scrollt. Geen effect buiten
// blog-detailpagina's, want dan bestaat het element gewoon niet.
export function initReadingProgress() {
    const bar = document.querySelector('[data-reading-progress]');
    const target = document.querySelector('[data-reading-progress-target]');
    if (! bar || ! target) return;

    function update() {
        const rect = target.getBoundingClientRect();
        const total = rect.height - window.innerHeight;
        const scrolled = Math.min(Math.max(-rect.top, 0), Math.max(total, 1));
        const percent = total > 0 ? (scrolled / total) * 100 : 0;
        bar.style.width = `${Math.min(percent, 100)}%`;
    }

    document.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    update();
}
