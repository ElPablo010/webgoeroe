{{--
    Voegt de herkomst van de advertentie (UTM + sector uit de URL) toe aan wat de
    formulieren en de boeking meesturen (`tracking.attribution`). De pixel zelf en
    de rest van `tracking` komen uit <x-core::meta-pixel />; zonder Meta (feature uit)
    is er geen window.wgTracking en doet dit niets. Moet ná de core-pixel staan.
--}}
<script data-analytics="tracking-attribution">
    (function () {
        var t = window.wgTracking;
        if (! t || ! t.payload) return;
        var qs = new URLSearchParams(window.location.search);
        var attribution = {};
        ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'sector'].forEach(function (k) {
            if (qs.get(k)) attribution[k] = qs.get(k);
        });
        var payload = t.payload;
        t.payload = function () {
            var p = payload.apply(t, arguments);
            p.attribution = attribution;
            return p;
        };
    })();
</script>
