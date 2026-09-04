{{-- Voorgestelde keywords — inline styling, Filament laadt de app-Tailwind niet. --}}
<x-filament-widgets::widget>
    @php $items = $this->suggestions(); @endphp
    <x-filament::section
        heading="Voorgestelde keywords"
        :description="'Uit het keyword-onderzoek' . ($this->generatedAt() ? ' van ' . $this->generatedAt() : '') . '. Vink aan wat je wilt opvolgen — elke opgevolgde keyword kost wekelijks een meting.'"
        collapsible
    >
        @if ($items === [])
            <div style="font-size:.875rem;color:rgb(107 114 128);">Alle voorstellen zijn al opgevolgd of gewist.</div>
        @else
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(16rem,1fr));gap:.375rem .75rem;">
                @foreach ($items as $item)
                    <label style="display:flex;align-items:center;gap:.5rem;font-size:.875rem;cursor:pointer;padding:.25rem 0;">
                        <x-filament::input.checkbox wire:model="selected" value="{{ $item['keyword'] }}" />
                        <span style="flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $item['keyword'] }}</span>
                        <span style="color:rgb(107 114 128);font-size:.75rem;flex-shrink:0;">{{ $item['search_volume'] !== null ? number_format((int) $item['search_volume'], 0, ',', '.') . '/mnd' : 'volume onbekend' }}</span>
                    </label>
                @endforeach
            </div>
            <div style="display:flex;gap:.5rem;margin-top:1rem;flex-wrap:wrap;">
                <x-filament::button wire:click="addSelected" size="sm" icon="heroicon-o-plus">
                    Geselecteerde opvolgen
                </x-filament::button>
                <x-filament::button wire:click="discardAll" wire:confirm="Alle voorstellen wissen? Je kunt later opnieuw keywords laten voorstellen." size="sm" color="gray" outlined>
                    Voorstellen wissen
                </x-filament::button>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
