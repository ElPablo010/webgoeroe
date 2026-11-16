<div>
    {{-- Formulier van Website → Formulieren, in de stijl van het contactformulier.
         Logica volgt de core (0.20+). Weergaven ($layout): inline = in de pagina;
         brief = een beknopte balk die bij verzenden het volledige formulier als
         popup opent; popup = enkel de popup (knop of link naar #form-<id>). --}}
    @if ($layout === 'inline')
        @if ($sent)
            @include('livewire.forms.partials.custom-form-success')
        @elseif ($form)
            @include('livewire.forms.partials.custom-form-body', ['context' => 'inline'])
        @endif
    @elseif ($form)
        @if ($layout === 'brief')
            @if ($sent)
                @include('livewire.forms.partials.custom-form-success')
            @else
                @php
                    $briefFields = collect($fields)->filter(fn ($field) => $field['brief']);
                @endphp
                <form wire:submit="expand" wire:key="custom-form-{{ $form->getKey() }}-bar" class="grid items-end gap-4 sm:grid-cols-2 lg:flex lg:flex-wrap">
                    @foreach ($briefFields as $field)
                        @include('livewire.forms.partials.custom-form-field', ['id' => $form->getKey(), 'field' => $field, 'context' => 'bar'])
                    @endforeach
                    <div>
                        <button type="submit" wire:loading.attr="disabled" wire:target="expand"
                                class="cursor-pointer rounded-full bg-white px-6 py-3 text-sm font-semibold text-[#050507] transition-all hover:bg-white/90 disabled:cursor-not-allowed disabled:opacity-60">
                            {{ $form->brief_label ?: __('Verder') }}
                        </button>
                    </div>
                </form>
            @endif
        @endif

        <div x-data
             style="display: none;"
             x-show="$wire.open"
             x-effect="document.body.classList.toggle('overflow-hidden', $wire.open)"
             @keydown.escape.window="$wire.open = false"
             @if ($layout === 'popup') @core-form-open.window="if ($event.detail.id == {{ $form->getKey() }}) $wire.open = true" @endif
             class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/70 p-4 backdrop-blur-sm sm:items-center"
             role="dialog" aria-modal="true" aria-label="{{ $form->name }}" data-core-form-popup="{{ $form->getKey() }}">
            <div class="relative my-8 w-full max-w-2xl rounded-2xl border border-white/[0.08] bg-[#0b0b10] p-6 text-left text-white shadow-2xl sm:p-8" @click.outside="$wire.open = false">
                <button type="button" @click="$wire.open = false" class="absolute right-4 top-4 cursor-pointer rounded-full p-2 text-white/50 hover:bg-white/[0.06] hover:text-white" aria-label="{{ __('Sluiten') }}">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h2 class="mb-6 pr-8 text-xl font-bold">{{ $form->name }}</h2>

                @if ($sent)
                    @include('livewire.forms.partials.custom-form-success')
                @else
                    @include('livewire.forms.partials.custom-form-body', ['context' => 'modal'])
                @endif
            </div>
        </div>
    @endif

    @if ($layout !== 'inline')
        @script
        <script>
            @if ($layout === 'popup' && $form)
                if (window.location.hash === '#form-{{ $form->getKey() }}') $wire.open = true;
            @endif

            // Eén keer per pagina: elke link naar #form-<id> of knop met data-core-form="<id>" opent dat formulier.
            if (! window.coreFormTriggers) {
                window.coreFormTriggers = true;
                document.addEventListener('click', (event) => {
                    const trigger = event.target.closest('a[href^="#form-"], [data-core-form]');
                    if (! trigger) return;
                    const id = trigger.dataset.coreForm || trigger.getAttribute('href').replace('#form-', '');
                    event.preventDefault();
                    window.dispatchEvent(new CustomEvent('core-form-open', { detail: { id } }));
                });
            }
        </script>
        @endscript
    @endif
</div>
