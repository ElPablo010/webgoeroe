<div>
    {{-- Formulier van Website → Formulieren (core 0.15.13), in de stijl van het contactformulier. --}}
    @if ($sent)
        <div class="rounded-2xl border border-white/[0.08] bg-white/[0.04] p-8 text-center" role="status">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full border border-cyan-400/20 bg-cyan-400/[0.10]">
                <svg class="h-7 w-7 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h3 class="text-lg font-bold text-white">Aanvraag ontvangen!</h3>
            <p class="mt-2 text-sm text-white/50">{{ $sentMessage ?: 'We nemen zo snel mogelijk contact met je op.' }}</p>
        </div>
    @elseif ($form)
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

        @php
            $id = $form->getKey();
            $input = 'w-full rounded-xl border border-white/[0.08] bg-white/[0.04] px-4 py-3 text-sm text-white placeholder-white/20 focus:border-cyan-400/40 focus:outline-none focus:ring-2 focus:ring-cyan-400/10';
        @endphp

        <form wire:submit="submit" wire:key="custom-form-{{ $id }}" class="grid gap-4 sm:grid-cols-2">
            {{-- Honeypot --}}
            <div class="hidden" aria-hidden="true">
                <input type="text" wire:model="website" tabindex="-1" autocomplete="off">
            </div>

            @foreach ($form->cleanFields() as $field)
                @php
                    $model = "answers.{$id}.{$field['key']}";
                    $inputId = "cf-{$id}-{$field['key']}";
                @endphp
                <div class="{{ $field['width'] === 'half' ? '' : 'sm:col-span-2' }}">
                    @if ($field['type'] === 'checkboxes')
                        <p class="mb-1.5 block text-sm font-medium text-white/60">
                            {{ $field['label'] }} @if ($field['required'])<span class="text-red-400">*</span>@endif
                        </p>
                        <div class="grid gap-2 sm:grid-cols-2">
                            @foreach ($field['options'] as $option)
                                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-white/[0.08] bg-white/[0.04] px-4 py-3 text-sm text-white/80 has-[:checked]:border-cyan-400/40 has-[:checked]:bg-cyan-400/[0.07]">
                                    <input type="checkbox" value="{{ $option }}" wire:model="{{ $model }}" class="h-4 w-4 rounded border-white/20 bg-transparent text-cyan-400 focus:ring-cyan-400/20">
                                    {{ $option }}
                                </label>
                            @endforeach
                        </div>
                    @else
                        <label for="{{ $inputId }}" class="mb-1.5 block text-sm font-medium text-white/60">
                            {{ $field['label'] }} @if ($field['required'])<span class="text-red-400">*</span>@endif
                        </label>

                        @if ($field['type'] === 'textarea')
                            <textarea id="{{ $inputId }}" rows="4" wire:model="{{ $model }}" placeholder="{{ $field['placeholder'] }}" class="{{ $input }}"></textarea>
                        @elseif ($field['type'] === 'select')
                            <select id="{{ $inputId }}" wire:model="{{ $model }}" class="{{ $input }} [&>option]:bg-[#0b0b10]">
                                <option value="">Maak een keuze</option>
                                @foreach ($field['options'] as $option)
                                    <option value="{{ $option }}">{{ $option }}</option>
                                @endforeach
                            </select>
                        @elseif ($field['type'] === 'file')
                            <input id="{{ $inputId }}" type="file" multiple wire:model="{{ $model }}"
                                   accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,.doc,.docx,.xls,.xlsx"
                                   class="block w-full cursor-pointer rounded-xl border border-dashed border-white/[0.12] bg-white/[0.02] px-4 py-3 text-sm text-white/60 file:mr-3 file:cursor-pointer file:rounded-full file:border-0 file:bg-white/10 file:px-4 file:py-1.5 file:text-sm file:font-medium file:text-white">
                            <p class="mt-1 text-xs text-white/40" wire:loading.remove wire:target="{{ $model }}">Pdf, foto, Word of Excel, max. 10 MB per bestand.</p>
                            <p class="mt-1 text-xs text-cyan-400" wire:loading wire:target="{{ $model }}">Bezig met uploaden…</p>
                        @else
                            {{-- Website als tekst: type="url" weigert "www.bedrijf.be" zonder https:// en blokkeert dan stil het versturen. --}}
                            <input id="{{ $inputId }}" type="{{ $field['type'] === 'url' ? 'text' : $field['type'] }}" @if ($field['type'] === 'url') inputmode="url" autocomplete="url" @endif wire:model="{{ $model }}" placeholder="{{ $field['placeholder'] }}"
                                   @if ($field['type'] === 'email') autocomplete="email" @elseif ($field['type'] === 'tel') autocomplete="tel" @elseif ($field['key'] === 'name') autocomplete="name" @endif
                                   class="{{ $input }}">
                        @endif
                    @endif

                    @error($model) <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    @error($model.'.*') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                </div>
            @endforeach

            <div class="sm:col-span-2">
                <button type="submit" wire:loading.attr="disabled" wire:target="submit"
                        class="w-full cursor-pointer rounded-full bg-white px-6 py-3.5 text-sm font-semibold text-[#050507] transition-all hover:bg-white/90 disabled:cursor-not-allowed disabled:opacity-60">
                    <span wire:loading.remove wire:target="submit">{{ $form->button_label ?: 'Versturen' }}</span>
                    <span wire:loading wire:target="submit" class="inline-flex items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        Versturen...
                    </span>
                </button>
            </div>
        </form>
    @endif
</div>
