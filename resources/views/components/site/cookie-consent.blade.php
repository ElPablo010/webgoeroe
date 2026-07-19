{{--
    Cookie-consent banner + voorkeuren-paneel. Puur client-side (Alpine): de
    keuze wordt bewaard in een cookie `cookie_consent` (180 dagen). Toekomstige
    tracking-scripts (Google Analytics, Facebook Pixel, LinkedIn Insight Tag, …)
    checken vóór het laden `window.cookieConsent.has('analytics' | 'marketing')`,
    of luisteren naar het `cookie-consent-changed` window-event.

    Heropenen vanaf elders (bv. de "Cookie-instellingen"-link in de footer):
    window.dispatchEvent(new CustomEvent('open-cookie-preferences'))
--}}
<div
    x-data="{
        open: false,
        detailed: false,
        prefs: { analytics: true, marketing: true },

        init() {
            const stored = this.readConsent();
            if (stored) {
                this.prefs.analytics = !! stored.analytics;
                this.prefs.marketing = !! stored.marketing;
                this.applyConsent(stored);
            } else {
                this.open = true;
            }

            window.addEventListener('open-cookie-preferences', () => {
                this.detailed = true;
                this.open = true;
            });
        },

        readConsent() {
            const match = document.cookie.match(/(?:^|; )cookie_consent=([^;]*)/);
            if (! match) return null;
            try {
                return JSON.parse(decodeURIComponent(match[1]));
            } catch (e) {
                return null;
            }
        },

        applyConsent(consent) {
            window.cookieConsent = {
                functional: true,
                analytics: !! consent.analytics,
                marketing: !! consent.marketing,
                has(category) {
                    return category === 'functional' ? true : !! this[category];
                },
            };
            window.dispatchEvent(new CustomEvent('cookie-consent-changed', { detail: window.cookieConsent }));
        },

        saveConsent(consent) {
            const value = encodeURIComponent(JSON.stringify(consent));
            const maxAge = 60 * 60 * 24 * 180; // 180 dagen
            document.cookie = `cookie_consent=${value}; max-age=${maxAge}; path=/; samesite=lax`;
            this.applyConsent(consent);
            this.open = false;
            // Pas resetten nadat de banner is uitgeschoven, anders zie je het
            // voorkeuren-paneel eerst apart wegvallen tijdens de leave-transitie.
            setTimeout(() => this.detailed = false, 300);
        },

        acceptAll() {
            this.prefs.analytics = true;
            this.prefs.marketing = true;
            this.saveConsent({ functional: true, analytics: true, marketing: true });
        },

        rejectAll() {
            this.prefs.analytics = false;
            this.prefs.marketing = false;
            this.saveConsent({ functional: true, analytics: false, marketing: false });
        },

        savePreferences() {
            this.saveConsent({ functional: true, analytics: this.prefs.analytics, marketing: this.prefs.marketing });
        },
    }"
    x-show="open"
    x-cloak
    x-transition:enter="transition-opacity ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition-opacity ease-in duration-300"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    class="fixed inset-x-0 bottom-0 z-[100] flex justify-center px-4 pb-4 sm:px-6"
    role="dialog"
    aria-modal="true"
    aria-label="Cookievoorkeuren"
>
    <div class="w-full max-w-2xl rounded-2xl border border-white/[0.08] bg-[#0a0a0d] p-6 shadow-2xl shadow-black/50">
        <div class="mb-3 flex items-start gap-3">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-cyan-400/10 text-cyan-400">
                <x-lucide-cookie class="h-4.5 w-4.5" />
            </span>
            <div>
                <div class="font-semibold text-white">Wij respecteren jouw privacy</div>
                <p class="mt-1 text-sm text-white/50">
                    We gebruiken cookies om onze site goed te laten werken en, met jouw toestemming, om bezoekersstatistieken
                    te verzamelen. Lees ons <a href="/cookiebeleid" class="text-cyan-400 hover:underline">cookiebeleid</a> voor meer info.
                </p>
            </div>
        </div>

        <div
            {{-- Geen eigen x-transition: het paneel moet mee in-/uitfaden met de
                 banner als geheel, niet als een tweede animatie erbovenop. --}}
            x-show="detailed"
            x-cloak
            class="mt-4 space-y-3 rounded-xl border border-white/[0.06] bg-white/[0.03] p-4"
        >
            <div class="flex items-center justify-between gap-4">
                <div>
                    <div class="text-sm font-medium text-white/80">Functionele cookies</div>
                    <div class="text-xs text-white/40">Noodzakelijk voor de werking van de website</div>
                </div>
                <span class="text-xs font-medium text-white/30">Altijd actief</span>
            </div>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <div class="text-sm font-medium text-white/80">Analytische cookies</div>
                    <div class="text-xs text-white/40">Helpen ons begrijpen hoe bezoekers de site gebruiken</div>
                </div>
                <button
                    type="button"
                    role="switch"
                    :aria-checked="prefs.analytics"
                    @click="prefs.analytics = ! prefs.analytics"
                    class="relative h-6 w-11 shrink-0 cursor-pointer rounded-full transition-colors"
                    :class="prefs.analytics ? 'bg-cyan-500' : 'bg-white/10'"
                >
                    <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white transition-transform" :class="prefs.analytics ? 'translate-x-5' : 'translate-x-0'"></span>
                </button>
            </div>
            <div class="flex items-center justify-between gap-4">
                <div>
                    <div class="text-sm font-medium text-white/80">Marketing cookies</div>
                    <div class="text-xs text-white/40">Worden gebruikt om gepersonaliseerde advertenties te tonen</div>
                </div>
                <button
                    type="button"
                    role="switch"
                    :aria-checked="prefs.marketing"
                    @click="prefs.marketing = ! prefs.marketing"
                    class="relative h-6 w-11 shrink-0 cursor-pointer rounded-full transition-colors"
                    :class="prefs.marketing ? 'bg-cyan-500' : 'bg-white/10'"
                >
                    <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white transition-transform" :class="prefs.marketing ? 'translate-x-5' : 'translate-x-0'"></span>
                </button>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
            <button
                type="button"
                @click="detailed = ! detailed"
                class="flex cursor-pointer items-center gap-1 text-sm font-medium text-white/50 transition-colors hover:text-white"
            >
                <x-lucide-chevron-up x-show="detailed" class="h-4 w-4" />
                <x-lucide-chevron-down x-show="! detailed" class="h-4 w-4" />
                <span x-text="detailed ? 'Minder' : 'Voorkeuren aanpassen'"></span>
            </button>

            <div class="flex flex-wrap gap-2">
                <button x-show="detailed" type="button" @click="savePreferences()" class="cursor-pointer rounded-lg border border-white/10 px-4 py-2 text-sm font-medium text-white/70 transition-colors hover:bg-white/[0.06]">
                    Voorkeuren opslaan
                </button>
                <button type="button" @click="rejectAll()" class="cursor-pointer rounded-lg border border-white/10 px-4 py-2 text-sm font-medium text-white/70 transition-colors hover:bg-white/[0.06]">
                    Weigeren
                </button>
                <button type="button" @click="acceptAll()" class="cursor-pointer rounded-lg bg-cyan-400 px-4 py-2 text-sm font-semibold text-[#050507] transition-colors hover:bg-cyan-300">
                    Accepteren
                </button>
            </div>
        </div>
    </div>
</div>
