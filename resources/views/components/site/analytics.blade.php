{{--
    Google Analytics 4, consent-gated. Het measurement-ID (G-XXXXXXX) komt uit
    Instellingen → Algemeen (Setting `google_analytics_id`); leeg = niets laden.

    gtag.js wordt pas van Google opgehaald nadat de bezoeker analytische cookies
    aanvaardt in de cookiebanner (<x-site.cookie-consent>): vóór die keuze gaat
    er geen enkel verzoek naar Google, ook geen "cookieloze ping". Trekt de
    bezoeker de toestemming later in, dan wordt GA uitgeschakeld en worden de
    _ga-cookies verwijderd.

    Dit script staat in <head> (vóór Alpine): de listener op
    `cookie-consent-changed` staat zo al klaar wanneer de banner bij het
    initialiseren een eerder bewaarde keuze doorgeeft.
--}}
@php
    $gaId = trim((string) \App\Models\Setting::get('google_analytics_id', ''));
@endphp

@if ($gaId !== '')
    <script data-analytics="ga4">
        (function () {
            var id = @js($gaId);
            var loaded = false;

            function enable() {
                window['ga-disable-' + id] = false;
                if (loaded) return;
                loaded = true;

                window.dataLayer = window.dataLayer || [];
                window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
                gtag('consent', 'default', {
                    analytics_storage: 'granted',
                    ad_storage: 'denied',
                    ad_user_data: 'denied',
                    ad_personalization: 'denied',
                });
                gtag('js', new Date());
                gtag('config', id);

                var s = document.createElement('script');
                s.async = true;
                s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
                document.head.appendChild(s);
            }

            function disable() {
                window['ga-disable-' + id] = true;
                var host = location.hostname.replace(/^www\./, '');
                document.cookie.split(';').forEach(function (c) {
                    var name = c.split('=')[0].trim();
                    if (name === '_ga' || name.indexOf('_ga_') === 0 || name === '_gid') {
                        ['', host, '.' + host].forEach(function (d) {
                            document.cookie = name + '=; max-age=0; path=/' + (d ? '; domain=' + d : '');
                        });
                    }
                });
            }

            function apply(consent) {
                if (consent && consent.analytics) { enable(); } else { disable(); }
            }

            window.addEventListener('cookie-consent-changed', function (e) { apply(e.detail); });
            if (window.cookieConsent) { apply(window.cookieConsent); }
        })();
    </script>
@endif
