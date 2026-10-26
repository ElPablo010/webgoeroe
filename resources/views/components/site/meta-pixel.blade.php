{{--
    Meta-pixel, consent-gestuurd (marketingcookies in <x-site.cookie-consent>).
    Pixel-ID: config('services.meta.pixel_id') (.env META_PIXEL_ID); leeg = niets laden.
    Vóór toestemming gaat er geen verzoek naar Meta; intrekken schakelt de pixel
    uit en wist _fbp/_fbc. Staat in <head> vóór Alpine, zoals de GA4-snippet.

    Daarnaast zet dit script `window.wgTracking`:
    - payload(): wat de formulieren (omzetcheck, boeking) meesturen naar de server:
      herkomst (UTM + sector uit de URL), toestemming, _fbp/_fbc en een event_id;
    - fire(naam, payload): zelfde event in de browser, met hetzelfde event_id als
      het serverevent (Meta ontdubbelt daarop).
    Herkomst zonder cookies: staat enkel in de URL van de pagina en gaat mee met de
    inzending van de bezoeker zelf.
--}}
@php
    $pixelId = trim((string) config('services.meta.pixel_id'));
@endphp

<script data-analytics="meta-pixel">
    (function () {
        var id = @js($pixelId);
        var loaded = false;
        var qs = new URLSearchParams(window.location.search);
        var attribution = {};
        ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'sector'].forEach(function (k) {
            if (qs.get(k)) attribution[k] = qs.get(k);
        });

        function consented() { return !! (window.cookieConsent && window.cookieConsent.marketing); }
        function cookie(name) {
            var m = document.cookie.match('(?:^|; )' + name + '=([^;]*)');
            return m ? decodeURIComponent(m[1]) : null;
        }
        function uuid() {
            return (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : String(Date.now()) + Math.random().toString(16).slice(2);
        }

        window.wgTracking = {
            payload: function () {
                var ok = consented();
                return {
                    consent: ok,
                    event_id: uuid(),
                    fbp: ok ? cookie('_fbp') : null,
                    fbc: ok ? (cookie('_fbc') || (qs.get('fbclid') ? 'fb.1.' + Date.now() + '.' + qs.get('fbclid') : null)) : null,
                    attribution: attribution
                };
            },
            fire: function (name, payload) {
                if (loaded && window.fbq && payload) fbq('track', name, {}, { eventID: payload.event_id });
            }
        };

        if (id === '') return;

        function enable() {
            if (loaded) return;
            loaded = true;
            !function (f, b, e, v, n, t, s) {
                if (f.fbq) return; n = f.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments) };
                if (!f._fbq) f._fbq = n; n.push = n; n.loaded = !0; n.version = '2.0'; n.queue = []; t = b.createElement(e); t.async = !0;
                t.src = v; s = b.getElementsByTagName(e)[0]; s.parentNode.insertBefore(t, s)
            }(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
            fbq('init', id);
            fbq('track', 'PageView');
        }

        function disable() {
            if (window.fbq) fbq('consent', 'revoke');
            var host = location.hostname.replace(/^www\./, '');
            ['_fbp', '_fbc'].forEach(function (name) {
                ['', host, '.' + host].forEach(function (d) {
                    document.cookie = name + '=; max-age=0; path=/' + (d ? '; domain=' + d : '');
                });
            });
        }

        function apply(consent) {
            if (consent && consent.marketing) { enable(); } else { disable(); }
        }

        window.addEventListener('cookie-consent-changed', function (e) { apply(e.detail); });
        if (window.cookieConsent) { apply(window.cookieConsent); }
    })();
</script>
