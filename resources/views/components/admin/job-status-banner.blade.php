{{--
    De voortgang van een achtergrondtaak die vanuit een knop in de admin
    gestart is (App\Support\JobStatus).

    Drie gezichten: bezig (met spinner, het blok eromheen ververst zichzelf),
    blijven hangen (de wachtrij-worker draait niet) en mislukt (met de reden
    die de job wegschreef). Bij "klaar" toont dit niets — dan spreekt het
    resultaat eronder voor zich.

    Inline styling: Filament laadt de app-Tailwind niet in de admin.

    @param \App\Support\JobStatus $status
    @param string $busyTitle      kop terwijl de taak draait
    @param string $queuedTitle    kop terwijl ze nog in de wachtrij staat
    @param string $busyBody       uitleg tijdens het wachten
    @param string $failedTitle    kop wanneer ze niets opleverde
    @param ?string $dismiss       Livewire-methode om de melding weg te klikken
--}}
@props([
    'status',
    'busyTitle' => 'Dit loopt nog…',
    'queuedTitle' => 'Dit staat in de wachtrij…',
    'busyBody' => 'Dit duurt enkele minuten; je mag dit scherm gerust verlaten — de stand staat er bij terugkomst nog.',
    'failedTitle' => 'Dit leverde niets op',
    'dismiss' => null,
])

@php
    $state = $status->state();
    $busy = $status->isBusy();
    $since = $status->since();

    $banner = match (true) {
        $busy => [
            'tone' => 'rgb(124 58 237)',
            'bg' => 'rgba(124,58,237,.08)',
            'title' => $state === \App\Support\JobStatus::RUNNING ? $busyTitle : $queuedTitle,
            'body' => $busyBody . ($since ? ' Gestart om ' . $since->format('H:i') . '.' : ''),
        ],
        $status->isStale() => [
            'tone' => 'rgb(217 119 6)',
            'bg' => 'rgba(217,119,6,.08)',
            'title' => 'De taak is blijven hangen',
            'body' => 'Ze staat al ' . ($since ? (int) $since->diffInMinutes(now()) . ' minuten' : 'een tijd')
                . ' te wachten zonder opgepikt te worden. Bijna altijd betekent dat dat de wachtrij-worker niet draait '
                . '(de cron die elke minuut `php artisan schedule:run` uitvoert). '
                . 'Draai `php artisan queue:work --stop-when-empty` op de server om te zien wat er blijft steken.',
        ],
        $state === \App\Support\JobStatus::FAILED => [
            'tone' => 'rgb(220 38 38)',
            'bg' => 'rgba(220,38,38,.08)',
            'title' => $failedTitle,
            'body' => $status->message() ?? 'Onbekende fout.',
        ],
        default => null,
    };
@endphp

@if ($banner)
    <div style="display:flex;gap:.75rem;align-items:flex-start;padding:.75rem 1rem;border-radius:.5rem;border:1px solid {{ $banner['tone'] }};background:{{ $banner['bg'] }};">
        @if ($busy)
            <x-filament::loading-indicator style="height:1.25rem;width:1.25rem;flex-shrink:0;color:{{ $banner['tone'] }};" />
        @else
            <x-filament::icon icon="heroicon-o-exclamation-triangle" style="height:1.25rem;width:1.25rem;flex-shrink:0;color:{{ $banner['tone'] }};" />
        @endif

        <div style="flex:1;min-width:0;">
            <div style="font-size:.875rem;font-weight:600;color:{{ $banner['tone'] }};">{{ $banner['title'] }}</div>
            <div style="font-size:.8125rem;margin-top:.125rem;color:rgb(107 114 128);">{{ $banner['body'] }}</div>
        </div>

        @if ($dismiss && ! $busy)
            <x-filament::icon-button
                wire:click="{{ $dismiss }}"
                icon="heroicon-o-x-mark"
                color="gray"
                size="sm"
                label="Melding sluiten"
            />
        @endif
    </div>
@endif
