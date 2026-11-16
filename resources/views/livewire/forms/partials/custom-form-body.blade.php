@php
    /*
     * Het formulier zelf in de stijl van WebGoeroe (logica volgt core 0.20+:
     * stappen met voortgangsbalk, eigen veldsoorten). Variabelen: $form, $forms,
     * $steps, $fields, $step, $active, $context ('inline' of 'modal').
     */
    $id = $form->getKey();
    $last = max(1, count($steps)) - 1;
    $visible = collect($fields)->filter(fn ($field) => count($steps) === 0 || (int) $field['step'] === $step + 1);
@endphp

@if ($forms->count() > 1)
    <div class="mb-6 grid grid-cols-2 gap-1 rounded-full border border-white/[0.08] bg-white/[0.04] p-1" role="tablist">
        @foreach ($forms as $index => $tab)
            <button type="button" role="tab" wire:click="show({{ $index }})" aria-selected="{{ $index === $active ? 'true' : 'false' }}"
                    class="cursor-pointer rounded-full px-4 py-2.5 text-sm font-semibold transition-colors {{ $index === $active ? 'bg-white text-[#050507]' : 'text-white/60 hover:text-white' }}">
                {{ $tab->tab_label ?: $tab->name }}
            </button>
        @endforeach
    </div>
@endif


<form wire:submit="submit" wire:key="custom-form-{{ $id }}-{{ $context }}" class="grid gap-4 sm:grid-cols-2">
    @if (count($steps) > 1)
        <div class="sm:col-span-2" data-core-form-progress>
            <p class="mb-2 text-sm font-medium text-white/70">
                {{ __('Stap :current van :total', ['current' => $step + 1, 'total' => count($steps)]) }}
                @if (filled($steps[$step]['title'] ?? null))<span class="text-white/40">— {{ $steps[$step]['title'] }}</span>@endif
            </p>
            <div class="h-1.5 w-full overflow-hidden rounded-full bg-white/[0.08]" role="progressbar" aria-valuemin="1" aria-valuemax="{{ count($steps) }}" aria-valuenow="{{ $step + 1 }}">
                <div class="h-full rounded-full bg-cyan-400" style="width: {{ round((($step + 1) / count($steps)) * 100) }}%"></div>
            </div>
        </div>
    @endif

    {{-- Honeypot --}}
    <div class="hidden" aria-hidden="true">
        <input type="text" wire:model="website" tabindex="-1" autocomplete="off">
    </div>

    @foreach ($visible as $field)
        @include('livewire.forms.partials.custom-form-field', ['id' => $id, 'field' => $field, 'context' => $context])
    @endforeach

    <div class="flex flex-wrap items-center gap-3 sm:col-span-2">
        @if ($step > 0)
            <button type="button" wire:click="back" class="cursor-pointer rounded-full border border-white/10 bg-white/[0.06] px-6 py-3.5 text-sm font-semibold text-white/70 transition-colors hover:bg-white/10 hover:text-white">
                {{ __('Vorige') }}
            </button>
        @endif

        <button type="submit" wire:loading.attr="disabled" wire:target="submit,next"
                class="flex-1 cursor-pointer rounded-full bg-white px-6 py-3.5 text-sm font-semibold text-[#050507] transition-all hover:bg-white/90 disabled:cursor-not-allowed disabled:opacity-60">
            @if ($step < $last)
                <span>{{ __('Volgende') }}</span>
            @else
                <span wire:loading.remove wire:target="submit">{{ $form->button_label ?: 'Versturen' }}</span>
                <span wire:loading wire:target="submit" class="inline-flex items-center gap-2">
                    <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    Versturen...
                </span>
            @endif
        </button>
    </div>
</form>
