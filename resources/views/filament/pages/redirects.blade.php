<x-filament-panels::page>
    <x-filament::section heading="Nieuwe redirect">
        <form wire:submit="create">
            {{ $this->form }}

            {{-- Inline-marge: app.css-utilities (mt-*) zitten niet in de
                 Filament-CSS-bundle, dus zetten we de marge expliciet. --}}
            <div style="margin-top: 1.5rem;">
                <x-filament::button type="submit">
                    Opslaan
                </x-filament::button>
            </div>
        </form>
    </x-filament::section>

    {{ $this->table }}
</x-filament-panels::page>
