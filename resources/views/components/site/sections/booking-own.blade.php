@props(['calendarId', 'sectionId' => null, 'fields' => [], 'prefill' => [], 'buttonClass' => ''])

{{-- Eigen boekingsscherm op een GoHighLevel-agenda: maandkalender, tijdstippen als
     knoppen (geen scrollbalk) en een kort formulier. Tijdstippen en boeking lopen
     via /boeking/tijdstippen en /boeking (BookingController). Alle tijden zijn Brusselse tijd. --}}
<div x-data="ownBooking({ calendar: @js($calendarId), section: @js($sectionId), fields: @js(collect($fields)->map(fn ($f) => ['key' => $f['key'], 'target' => $f['target']])->values()), prefill: @js($prefill), urls: { slots: @js(route('booking.slots')), store: @js(route('booking.store')) } })"
     @omzetcheck-unlocked.window="applyPrefill($event.detail)"
     class="mx-auto w-full max-w-3xl rounded-2xl bg-white p-5 text-left text-slate-900 shadow-xl sm:p-8">

    {{-- Gelukt --}}
    <div x-show="done" x-cloak class="py-8 text-center" aria-live="polite">
        <div class="mx-auto mb-4 inline-flex h-12 w-12 items-center justify-center rounded-full bg-emerald-100 text-emerald-600">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
        </div>
        <h3 class="text-xl font-extrabold">Je gesprek is ingepland</h3>
        <p class="mt-2 text-slate-600" x-text="longLabel(chosen)"></p>
        <p class="mt-4 text-sm text-slate-500">Je ontvangt zo meteen een bevestiging per e-mail, met de link om deel te nemen.</p>
    </div>

    <div x-show="! done">
        <div x-show="! chosen">
            <h3 class="text-lg font-extrabold">Kies een dag en uur</h3>
            <p class="mt-1 text-sm text-slate-500">30 minuten, online.</p>

            <div class="mt-5 grid gap-6 md:grid-cols-[minmax(0,1fr)_minmax(0,14rem)]">
                {{-- Maandkalender --}}
                <div>
                    <div class="mb-3 flex items-center justify-between">
                        <button type="button" @click="prevMonth()" :disabled="! canPrev" aria-label="Vorige maand"
                                class="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-full text-slate-600 hover:bg-slate-100 disabled:cursor-default disabled:opacity-30 disabled:hover:bg-transparent">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                        <span class="font-bold capitalize" x-text="monthLabel"></span>
                        <button type="button" @click="nextMonth()" :disabled="! canNext" aria-label="Volgende maand"
                                class="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-full text-slate-600 hover:bg-slate-100 disabled:cursor-default disabled:opacity-30 disabled:hover:bg-transparent">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>
                    <div class="grid grid-cols-7 gap-1 text-center text-xs font-semibold uppercase text-slate-400">
                        <template x-for="d in ['ma','di','wo','do','vr','za','zo']" :key="d"><span x-text="d"></span></template>
                    </div>
                    <div class="mt-1 grid grid-cols-7 gap-1" :class="loading ? 'opacity-50' : ''">
                        <template x-for="(cell, i) in cells" :key="i">
                            {{-- Lege cellen vóór de 1e van de maand blijven in de grid (invisible, niet x-show), anders schuift de weekdag op. --}}
                            <button type="button" :disabled="! cell || ! cell.available" @click="cell && pickDay(cell.date)"
                                    class="aspect-square rounded-full text-sm font-semibold transition"
                                    :class="! cell ? 'invisible' : cell.date === day ? 'bg-violet-600 text-white' : (cell && cell.available ? 'cursor-pointer bg-violet-50 text-violet-700 hover:bg-violet-100' : 'text-slate-300')"
                                    x-text="cell ? cell.n : ''"></button>
                        </template>
                    </div>
                    <p x-show="error" x-cloak class="mt-3 text-sm text-red-600" x-text="error"></p>
                    <p x-show="! loading && ! error && ! anyAvailable" x-cloak class="mt-3 text-sm text-slate-500">Geen vrije momenten in deze maand. Probeer de volgende maand.</p>
                </div>

                {{-- Tijdstippen --}}
                <div>
                    <p class="mb-2 text-sm font-semibold text-slate-600" x-text="day ? dayLabel(day) : 'Kies eerst een dag'"></p>
                    <div class="grid grid-cols-3 gap-2 md:grid-cols-2">
                        <template x-for="s in (days[day] || [])" :key="s">
                            <button type="button" @click="pickSlot(s)"
                                    class="cursor-pointer rounded-full border border-violet-300 px-2 py-2.5 text-sm font-bold text-violet-700 transition hover:bg-violet-600 hover:text-white"
                                    x-text="timeLabel(s)"></button>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        {{-- Formulier --}}
        <form x-show="chosen" x-cloak @submit.prevent="submit()" class="space-y-4">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-extrabold">Bijna klaar</h3>
                    <p class="mt-1 text-sm text-slate-600" x-text="longLabel(chosen)"></p>
                </div>
                <button type="button" @click="chosen = null; formError = ''" class="cursor-pointer text-sm font-semibold text-violet-700 hover:underline">Ander moment</button>
            </div>

            {{-- Velden uit de admin (boeking-blok → Formuliervelden), elk gekoppeld aan een GHL-veld. --}}
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ($fields as $field)
                    <label class="block text-sm font-semibold {{ $field['type'] === 'textarea' ? 'sm:col-span-2' : '' }}">
                        {{ $field['label'] }}
                        @unless ($field['required'])<span class="font-normal text-slate-400">(optioneel)</span>@endunless
                        @if ($field['type'] === 'textarea')
                            <textarea x-model="answers[@js($field['key'])]" rows="3" maxlength="2000" @required($field['required'])
                                      placeholder="{{ $field['placeholder'] }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 font-normal focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-200"></textarea>
                        @else
                            <input type="{{ $field['type'] }}" x-model="answers[@js($field['key'])]" @required($field['required'])
                                   maxlength="255" placeholder="{{ $field['placeholder'] }}"
                                   autocomplete="{{ ['first_name' => 'given-name', 'last_name' => 'family-name', 'email' => 'email', 'phone' => 'tel', 'website' => 'url'][$field['target']] ?? 'off' }}"
                                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2.5 font-normal focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-200">
                        @endif
                    </label>
                @endforeach
            </div>
            {{-- Honeypot --}}
            <input type="text" x-model="hp" tabindex="-1" autocomplete="off" aria-hidden="true" class="absolute -left-[9999px] h-0 w-0 opacity-0">

            <p x-show="formError" x-cloak class="text-sm font-semibold text-red-600" x-text="formError" role="alert"></p>

            <button type="submit" :disabled="sending"
                    class="inline-flex w-full cursor-pointer items-center justify-center bg-violet-600 px-8 py-4 text-base font-semibold text-white transition-all hover:-translate-y-px hover:bg-violet-500 hover:shadow-lg disabled:cursor-wait disabled:opacity-60 {{ $buttonClass }}"
                    style="border-radius:100px;">
                <span x-text="sending ? 'Even geduld…' : 'Bevestig mijn gesprek'"></span>
            </button>
        </form>
    </div>
</div>

@once
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('ownBooking', (cfg) => ({
                calendar: cfg.calendar,
                urls: cfg.urls,
                days: {},
                day: null,
                chosen: null,
                loading: true,
                error: '',
                formError: '',
                sending: false,
                done: false,
                // Eerste dag van de getoonde maand.
                cursor: new Date(new Date().getFullYear(), new Date().getMonth(), 1),
                today: new Date(),
                section: cfg.section,
                fields: cfg.fields,
                answers: Object.fromEntries(cfg.fields.map(f => [f.key, ''])),
                hp: '',

                init() { this.applyPrefill(cfg.prefill); this.load(); },

                // Voornaam en e-mail uit de omzetcheck vullen het overeenkomstige veld in.
                applyPrefill(d) {
                    for (const f of this.fields) {
                        if (['first_name', 'email'].includes(f.target) && d?.[f.target] && ! this.answers[f.key]) this.answers[f.key] = d[f.target];
                    }
                },

                pad(n) { return String(n).padStart(2, '0'); },
                ymd(d) { return d.getFullYear() + '-' + this.pad(d.getMonth() + 1) + '-' + this.pad(d.getDate()); },

                async load() {
                    this.loading = true;
                    this.error = '';
                    const from = this.cursor > this.today ? this.ymd(this.cursor) : this.ymd(this.today);
                    try {
                        const r = await fetch(this.urls.slots + '?calendar=' + encodeURIComponent(this.calendar) + '&from=' + from, { headers: { Accept: 'application/json' } });
                        if (! r.ok) throw new Error();
                        const j = await r.json();
                        this.days = { ...this.days, ...(j.days || {}) };
                        if (! this.day) this.autoPick();
                    } catch (e) {
                        this.error = 'De beschikbare momenten konden niet geladen worden. Probeer het later opnieuw.';
                    }
                    this.loading = false;
                },

                // Eerste vrije dag in de getoonde maand.
                autoPick() {
                    const first = Object.keys(this.days).sort().find(d => d.slice(0, 7) === this.ymd(this.cursor).slice(0, 7));
                    if (first) this.day = first;
                },

                get monthLabel() { return this.cursor.toLocaleDateString('nl-BE', { month: 'long', year: 'numeric' }); },
                get canPrev() { return this.cursor > new Date(this.today.getFullYear(), this.today.getMonth(), 1); },
                get canNext() { return this.cursor < new Date(this.today.getFullYear(), this.today.getMonth() + 2, 1); },
                get anyAvailable() { return Object.keys(this.days).some(d => d.slice(0, 7) === this.ymd(this.cursor).slice(0, 7)); },

                get cells() {
                    const y = this.cursor.getFullYear(), m = this.cursor.getMonth();
                    const lead = (new Date(y, m, 1).getDay() + 6) % 7; // maandag eerst
                    const count = new Date(y, m + 1, 0).getDate();
                    const out = Array(lead).fill(null);
                    for (let n = 1; n <= count; n++) {
                        const date = y + '-' + this.pad(m + 1) + '-' + this.pad(n);
                        out.push({ n, date, available: !! (this.days[date] && this.days[date].length) });
                    }
                    return out;
                },

                prevMonth() { if (! this.canPrev) return; this.cursor = new Date(this.cursor.getFullYear(), this.cursor.getMonth() - 1, 1); this.day = null; this.load(); },
                nextMonth() { if (! this.canNext) return; this.cursor = new Date(this.cursor.getFullYear(), this.cursor.getMonth() + 1, 1); this.day = null; this.load(); },
                pickDay(d) { this.day = d; },
                pickSlot(s) { this.chosen = s; this.formError = ''; },

                // De slots komen met Brusselse offset binnen ("2026-10-26T10:00:00+01:00"): toon het uur uit de tekst zelf.
                timeLabel(s) { return s.slice(11, 16); },
                dayLabel(d) { return new Date(d + 'T12:00:00').toLocaleDateString('nl-BE', { weekday: 'long', day: 'numeric', month: 'long' }); },
                longLabel(s) { return s ? this.dayLabel(s.slice(0, 10)) + ' om ' + this.timeLabel(s) : ''; },

                async submit() {
                    this.sending = true;
                    this.formError = '';
                    try {
                        const r = await fetch(this.urls.store, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                            body: JSON.stringify({ section: this.section, start: this.chosen, answers: this.answers, hp: this.hp }),
                        });
                        if (r.ok) { this.done = true; this.sending = false; return; }
                        if (r.status === 409) {
                            this.formError = 'Dit moment is net bezet. Kies een ander moment.';
                            this.chosen = null;
                            this.days = {};
                            this.day = null;
                            await this.load();
                        } else if (r.status === 422) {
                            this.formError = 'Controleer je gegevens: vul de verplichte velden in en een geldig e-mailadres.';
                        } else {
                            this.formError = 'Het boeken is niet gelukt. Probeer het opnieuw of mail naar pieter@dewebgoeroe.be.';
                        }
                    } catch (e) {
                        this.formError = 'Geen verbinding. Probeer het opnieuw.';
                    }
                    this.sending = false;
                },
            }));
        });
    </script>
@endonce
