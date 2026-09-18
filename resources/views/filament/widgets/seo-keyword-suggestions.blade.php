{{-- Voorgestelde keywords — inline styling, Filament laadt de app-Tailwind niet. --}}
@php
    $items = $this->suggestions();
    $status = $this->status();
    $busy = $status->isBusy();
    $showsBanner = $busy || $status->isStale() || $status->state() === \App\Support\JobStatus::FAILED;

    // Zolang er nog niets te kiezen valt slaat de gewone beschrijving ("vink
    // aan wat je wilt opvolgen") nergens op — dan legt de banner eronder uit
    // waar we staan.
    $description = $items === [] && $showsBanner
        ? 'Zoektermen die we voor je zoeken. Je kiest zelf wat je opvolgt — elke opgevolgde keyword kost wekelijks een meting.'
        : 'Uit het keyword-onderzoek' . ($this->generatedAt() ? ' van ' . $this->generatedAt() : '') . '. Vink aan wat je wilt opvolgen — elke opgevolgde keyword kost wekelijks een meting.';
@endphp

<x-filament-widgets::widget>
    <div @if ($this->pollInterval()) wire:poll.{{ $this->pollInterval() }} @endif>
        <x-filament::section
            heading="Voorgestelde keywords"
            :description="$description"
            collapsible
        >
            @if ($showsBanner)
                <div style="margin-bottom:1rem;">
                    <x-admin.job-status-banner
                        :status="$status"
                        busy-title="Keyword-onderzoek loopt…"
                        queued-title="Keyword-onderzoek staat in de wachtrij…"
                        busy-body="De AI bedenkt zoektermen en DataForSEO hangt er volumes aan. Dit duurt enkele minuten; je mag dit scherm gerust verlaten — de stand staat er bij terugkomst nog."
                        failed-title="Het onderzoek leverde niets op"
                        dismiss="dismissStatus"
                    />
                </div>
            @endif

            @if ($items === [])
                @unless ($busy)
                    <div style="font-size:.875rem;color:rgb(107 114 128);">Alle voorstellen zijn al opgevolgd of gewist.</div>
                @endunless
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
    </div>
</x-filament-widgets::widget>
