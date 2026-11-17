<div>
    {{-- Lekkencheck (0.15.10). Neutrale opmaak (publieke Tailwind); per project vrij
         te herontwerpen in resources/views/livewire/lead-check.blade.php. De logica
         (stappen in Alpine, submit(answers, tracking) in Livewire) blijft gelijk.
         Trackinghaakje: window.coreLeadTracking = { payload(), fire(naam, payload) }
         (anders window.coreBookingTracking). --}}
    @php
        $statusColors = ['green' => 'bg-green-500', 'orange' => 'bg-amber-500', 'red' => 'bg-red-500'];
        $statusLabels = ['green' => __('In orde'), 'orange' => __('Kan beter'), 'red' => __('Lek')];
        $period = $content['result_period'] ?? 'year';
    @endphp

    @if ($result)
        <div class="rounded-2xl border border-gray-200 bg-white p-6 text-gray-900 shadow-sm sm:p-8" role="status">
            @if ($result['mode'] === 'amount')
                <p class="text-2xl font-bold leading-snug sm:text-3xl">{{ $this->text($content['result_heading'] ?? '{voornaam}, je laat naar schatting {laag_jaar} tot {hoog_jaar} per jaar liggen.') }}</p>
                @if ($period === 'both')
                    <p class="mt-2 text-sm opacity-70">{{ $this->text('{laag_maand} tot {hoog_maand} per maand.') }}</p>
                @endif
            @else
                <p class="text-2xl font-bold leading-snug sm:text-3xl">{{ $this->text($content['result_heading_score'] ?? '{voornaam}, {score} van de {totaal} punten zijn in orde.') }}</p>
            @endif

            @if (filled($content['result_text'] ?? null))
                <p class="mt-4">{{ $this->text($content['result_text']) }}</p>
            @endif

            <ul class="mt-6 space-y-3">
                @foreach ($result['leaks'] as $leak)
                    <li class="flex gap-3">
                        <span class="mt-1.5 inline-block size-3 shrink-0 rounded-full {{ $statusColors[$leak['status']] }}" aria-hidden="true"></span>
                        <div>
                            <p class="font-medium">{{ $leak['label'] }} <span class="text-sm font-normal opacity-60">· {{ $statusLabels[$leak['status']] }}</span></p>
                            @if ($leak['advice'])
                                <p class="mt-0.5 text-sm opacity-80">{{ $leak['advice'] }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>

            @if ($result['price_clients'] !== null && filled($content['price_text'] ?? null))
                <p class="mt-6 font-medium">{{ $this->text($content['price_text']) }}</p>
            @endif

            @if (filled($content['cta_label'] ?? null))
                <a href="{{ \Webgoeroe\Core\Support\Url::resolveCtaHref($content, '#') }}" @if (! empty($content['new_tab'])) target="_blank" rel="noopener" @endif
                   class="mt-6 inline-flex items-center rounded-full bg-primary-600 px-6 py-3 font-medium text-white hover:bg-primary-700">
                    {{ $content['cta_label'] }}
                </a>
            @endif

            @if (filled($content['disclaimer'] ?? null))
                <p class="mt-6 text-xs opacity-60">{{ $content['disclaimer'] }}</p>
            @endif
        </div>
    @elseif ($questions === [])
        <p class="text-sm text-red-600">{{ __('Deze check heeft nog geen vragen.') }}</p>
    @else
        <div x-data="{
                questions: @js($questions),
                step: 0,
                answers: {},
                contact: { first_name: @js($first_name), email: @js($email), phone: @js($phone), website: '' },
                info: false,
                sending: false,
                get progress() { return Math.round((this.step / (this.questions.length + 1)) * 100) },
                {{-- Vaste stappen (i + 1, i - 1) in plaats van ++/--: laadt een site Alpine
                     twee keer, dan vuurt een klik twee keer en mag dat niets uitmaken. --}}
                choose(i, o) { this.answers[this.questions[i].key] = o; this.go(i + 1) },
                go(i) { this.info = false; this.step = Math.max(0, Math.min(i, this.questions.length)) },
                async send(form) {
                    if (form.dataset.sending) return;
                    form.dataset.sending = '1';
                    this.sending = true;
                    const hook = window.coreLeadTracking || window.coreBookingTracking || null;
                    const tracking = hook ? hook.payload() : null;
                    const component = window.Livewire.find(this.$root.closest('[wire\\:id]').getAttribute('wire:id'));
                    try {
                        const ok = await component.call('submit', this.answers, tracking, this.contact);
                        if (ok && hook) hook.fire('Lead', tracking);
                    } finally {
                        delete form.dataset.sending;
                        this.sending = false;
                    }
                },
            }" class="rounded-2xl border border-gray-200 bg-white p-6 text-gray-900 shadow-sm sm:p-8">
            {{-- Voortgang --}}
            <div class="mb-6 h-1.5 w-full overflow-hidden rounded-full bg-gray-100" aria-hidden="true">
                <div class="h-full rounded-full bg-primary-600 transition-all" :style="`width: ${progress}%`"></div>
            </div>

            {{-- Vragen --}}
            <template x-for="(q, i) in questions" :key="q.key">
                <div x-show="step === i" x-cloak>
                    <p class="text-sm opacity-60" x-text="`{{ __('Vraag') }} ${i + 1} {{ __('van') }} ${questions.length}`"></p>
                    <div class="mt-1 flex items-start gap-2">
                        <h3 class="text-xl font-semibold" x-text="q.question"></h3>
                        <button type="button" x-show="q.info" @click="info = ! info" class="mt-1 inline-flex size-5 shrink-0 items-center justify-center rounded-full border border-current text-xs opacity-60 hover:opacity-100" aria-label="{{ __('Meer uitleg') }}">i</button>
                    </div>
                    <p x-show="q.info && info" x-text="q.info" class="mt-2 rounded-lg bg-gray-50 p-3 text-sm opacity-80"></p>

                    <div x-show="q.input === 'choice'" class="mt-5 grid gap-2">
                        <template x-for="(label, o) in q.options" :key="o">
                            <button type="button" @click="choose(i, o)"
                                    class="rounded-xl border px-4 py-3 text-left transition hover:border-primary-600"
                                    :class="answers[q.key] === o ? 'border-primary-600 bg-primary-50' : 'border-gray-300'"
                                    x-text="label"></button>
                        </template>
                    </div>

                    <form x-show="q.input === 'number'" @submit.prevent="go(i + 1)" class="mt-5 flex items-center gap-2">
                        <span x-show="q.prefix" x-text="q.prefix"></span>
                        <input type="number" inputmode="decimal" step="any" :min="q.min ?? 0" :max="q.max ?? ''" x-model="answers[q.key]"
                               class="w-40 rounded-lg border border-gray-300 px-4 py-2.5 focus:border-primary-500 focus:ring-primary-500">
                        <span x-show="q.suffix" x-text="q.suffix"></span>
                        <button type="submit" :disabled="answers[q.key] === undefined || answers[q.key] === ''"
                                class="ml-2 rounded-full bg-primary-600 px-5 py-2.5 text-sm font-medium text-white disabled:opacity-50">{{ __('Volgende') }}</button>
                    </form>

                    <button type="button" x-show="i > 0" @click="go(i - 1)" class="mt-6 text-sm opacity-60 hover:opacity-100">← {{ __('Vorige') }}</button>
                </div>
            </template>

            {{-- Gegevens --}}
            <form x-show="step === questions.length" x-cloak @submit.prevent="send($el)" class="space-y-4">
                <h3 class="text-xl font-semibold">{{ $content['gate_heading'] ?? __('Waar mogen we je resultaat naartoe sturen?') }}</h3>
                @if (filled($content['gate_text'] ?? null))
                    <p class="opacity-80">{{ $content['gate_text'] }}</p>
                @endif

                <div class="hidden" aria-hidden="true">
                    <label>Laat dit veld leeg <input type="text" x-model="contact.website" tabindex="-1" autocomplete="off"></label>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="lc-first-name-{{ $sectionId }}" class="mb-1 block text-sm font-medium">{{ __('Voornaam') }}</label>
                        <input id="lc-first-name-{{ $sectionId }}" type="text" x-model="contact.first_name" autocomplete="given-name" required
                               class="w-full rounded-lg border border-gray-300 px-4 py-2.5 focus:border-primary-500 focus:ring-primary-500">
                        @error('first_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="lc-email-{{ $sectionId }}" class="mb-1 block text-sm font-medium">{{ __('E-mail') }}</label>
                        <input id="lc-email-{{ $sectionId }}" type="email" x-model="contact.email" autocomplete="email" required
                               class="w-full rounded-lg border border-gray-300 px-4 py-2.5 focus:border-primary-500 focus:ring-primary-500">
                        @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <label for="lc-phone-{{ $sectionId }}" class="mb-1 block text-sm font-medium">
                            {{ __('Gsm') }} @if (empty($content['phone_required']))<span class="opacity-60">{{ __('(optioneel)') }}</span>@endif
                        </label>
                        <input id="lc-phone-{{ $sectionId }}" type="tel" x-model="contact.phone" autocomplete="tel" @if (! empty($content['phone_required'])) required @endif
                               class="w-full rounded-lg border border-gray-300 px-4 py-2.5 focus:border-primary-500 focus:ring-primary-500">
                        @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </div>
                @error('answers') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                <button type="submit" :disabled="sending"
                        class="inline-flex items-center rounded-full bg-primary-600 px-6 py-3 font-medium text-white hover:bg-primary-700 disabled:opacity-60">
                    <span x-show="! sending">{{ $content['gate_button'] ?? __('Toon mijn resultaat') }}</span>
                    <span x-show="sending" x-cloak>{{ __('Even rekenen…') }}</span>
                </button>
                @if (filled($content['privacy_note'] ?? null))
                    <p class="text-xs opacity-60">{{ $content['privacy_note'] }}</p>
                @endif

                <button type="button" @click="go(questions.length - 1)" class="text-sm opacity-60 hover:opacity-100">← {{ __('Vorige') }}</button>
            </form>

        </div>
    @endif

</div>
