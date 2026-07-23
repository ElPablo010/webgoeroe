<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        {{-- Inline-marge: app.css-utilities worden niet in de Filament-CSS-bundle
             meegeleverd, dus zetten we de marge expliciet. --}}
        <div style="margin-top: 1.5rem;">
            <x-filament::button type="submit">
                Opslaan
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
