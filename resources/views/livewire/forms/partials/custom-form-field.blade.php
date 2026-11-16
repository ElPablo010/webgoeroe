@php
    /*
     * Eén veld in de stijl van WebGoeroe. Variabelen: $id (formulier), $field,
     * $context ('inline', 'bar' of 'modal': zo krijgen ids nooit een dubbel).
     */
    $model = "answers.{$id}.{$field['key']}";
    $inputId = "cf-{$id}-{$field['key']}-{$context}";
    $input = 'w-full rounded-xl border border-white/[0.08] bg-white/[0.04] px-4 py-3 text-sm text-white placeholder-white/20 focus:border-cyan-400/40 focus:outline-none focus:ring-2 focus:ring-cyan-400/10';
    $custom = \Webgoeroe\Core\Core::formFields()->get($field['type']);
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
        @if ($custom && $custom::hasOptions())
            <p class="mb-1.5 block text-sm font-medium text-white/60">
                {{ $field['label'] }} @if ($field['required'])<span class="text-red-400">*</span>@endif
            </p>
        @else
            <label for="{{ $inputId }}" class="mb-1.5 block text-sm font-medium text-white/60">
                {{ $field['label'] }} @if ($field['required'])<span class="text-red-400">*</span>@endif
            </label>
        @endif

        @if ($custom)
            @include($custom::view(), ['field' => $field, 'model' => $model, 'inputId' => $inputId, 'classes' => $input, 'context' => $context])
        @elseif ($field['type'] === 'textarea')
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
