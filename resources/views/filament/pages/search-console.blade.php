{{--
    Verkeer (Search Console) — Groei-meetlaag. Layout-kritische styling inline:
    Filament laadt de app-Tailwind niet.
--}}
<x-filament-panels::page>
    @php
        $gsc = app(\App\Services\GoogleSearchConsoleService::class);
        $ready = $this->tablesReady();
        $hasData = $this->hasData();
        $tables = $hasData ? $this->tables() : ['queries' => [], 'pages' => [], 'opportunities' => []];
        $th = 'padding:.5rem .75rem;font-weight:500;text-align:left;color:rgb(107 114 128);';
        $td = 'padding:.5rem .75rem;border-top:1px solid rgb(229 231 235);';
        $num = $td . 'text-align:right;white-space:nowrap;';
    @endphp

    @unless ($ready)
        <div style="border:1px solid rgb(252 211 77);background:rgb(255 251 235);color:rgb(120 53 15);border-radius:.75rem;padding:.75rem 1rem;font-size:.875rem;">
            De Search Console-tabellen bestaan nog niet. Draai <code>php artisan migrate</code>.
        </div>
    @elseif (! $gsc->hasOAuth() && $gsc->authMethod() === null)
        <div style="border:1px solid rgb(196 181 253);background:rgb(245 243 255);color:rgb(76 29 149);border-radius:.75rem;padding:.75rem 1rem;font-size:.875rem;line-height:1.5;">
            <strong>Nog niet gekoppeld.</strong> Vul hieronder de client-ID en het client-secret in, sla op, en klik dan op "Verbinden met Google".
            Search Console bewaart 16 maanden historiek — na het koppelen staat er meteen een echte trendlijn.
        </div>
    @elseif (! $hasData)
        <div style="border:1px solid rgb(196 181 253);background:rgb(245 243 255);color:rgb(76 29 149);border-radius:.75rem;padding:.75rem 1rem;font-size:.875rem;">
            Gekoppeld{{ $gsc->siteUrl !== '' ? ' met ' . $gsc->siteUrl : ', maar er is nog geen property gekozen' }}. {{ $gsc->siteUrl !== '' ? 'Klik op "Ververs nu" om de eerste 16 maanden in te lezen.' : 'Kies een site via "Andere site kiezen".' }}
        </div>
    @else
        <x-filament-widgets::widgets :widgets="$this->getWidgets()" :columns="1" />

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(22rem,1fr));gap:1rem;">
            <x-filament::section heading="Zoektermen" description="Waarop je gevonden wordt — laatste 28 dagen, op clicks.">
                @if (empty($tables['queries']))
                    <div style="font-size:.875rem;color:rgb(107 114 128);">Nog geen zoektermen in deze periode.</div>
                @else
                    <div style="overflow-x:auto;"><table style="width:100%;font-size:.875rem;border-collapse:collapse;">
                        <thead><tr><th style="{{ $th }}">Zoekterm</th><th style="{{ $th }}text-align:right;">Clicks</th><th style="{{ $th }}text-align:right;">Vert.</th><th style="{{ $th }}text-align:right;">Pos.</th></tr></thead>
                        <tbody>
                            @foreach ($tables['queries'] as $row)
                                <tr><td style="{{ $td }}">{{ $row['value'] }}</td><td style="{{ $num }}">{{ $row['clicks'] }}</td><td style="{{ $num }}">{{ number_format($row['impressions'], 0, ',', '.') }}</td><td style="{{ $num }}">{{ number_format($row['position'], 1, ',', '.') }}</td></tr>
                            @endforeach
                        </tbody>
                    </table></div>
                @endif
            </x-filament::section>

            <x-filament::section heading="Pagina's" description="Welke pagina's het verkeer binnenhalen — laatste 28 dagen.">
                @if (empty($tables['pages']))
                    <div style="font-size:.875rem;color:rgb(107 114 128);">Nog geen pagina's in deze periode.</div>
                @else
                    <div style="overflow-x:auto;"><table style="width:100%;font-size:.875rem;border-collapse:collapse;">
                        <thead><tr><th style="{{ $th }}">Pagina</th><th style="{{ $th }}text-align:right;">Clicks</th><th style="{{ $th }}text-align:right;">Vert.</th><th style="{{ $th }}text-align:right;">Pos.</th></tr></thead>
                        <tbody>
                            @foreach ($tables['pages'] as $row)
                                <tr><td style="{{ $td }}font-family:ui-monospace,monospace;font-size:.8125rem;word-break:break-all;">{{ preg_replace('#^https?://[^/]+#', '', $row['value']) ?: '/' }}</td><td style="{{ $num }}">{{ $row['clicks'] }}</td><td style="{{ $num }}">{{ number_format($row['impressions'], 0, ',', '.') }}</td><td style="{{ $num }}">{{ number_format($row['position'], 1, ',', '.') }}</td></tr>
                            @endforeach
                        </tbody>
                    </table></div>
                @endif
            </x-filament::section>
        </div>

        <x-filament::section heading="Kansen" description="Zoektermen met veel vertoningen op positie 4-20: je staat er wél, maar net niet hoog genoeg. Doorgaans de goedkoopste winst.">
            @if (empty($tables['opportunities']))
                <div style="font-size:.875rem;color:rgb(107 114 128);">Geen zoektermen die aan de kans-criteria voldoen (≥ 20 vertoningen, positie 4-20).</div>
            @else
                <div style="overflow-x:auto;"><table style="width:100%;font-size:.875rem;border-collapse:collapse;">
                    <thead><tr><th style="{{ $th }}">Zoekterm</th><th style="{{ $th }}text-align:right;">Vertoningen</th><th style="{{ $th }}text-align:right;">Clicks</th><th style="{{ $th }}text-align:right;">CTR</th><th style="{{ $th }}text-align:right;">Positie</th></tr></thead>
                    <tbody>
                        @foreach ($tables['opportunities'] as $row)
                            <tr><td style="{{ $td }}">{{ $row['query'] }}</td><td style="{{ $num }}">{{ number_format($row['impressions'], 0, ',', '.') }}</td><td style="{{ $num }}">{{ $row['clicks'] }}</td><td style="{{ $num }}">{{ number_format($row['ctr'], 1, ',', '.') }} %</td><td style="{{ $num }}">{{ number_format($row['position'], 1, ',', '.') }}</td></tr>
                        @endforeach
                    </tbody>
                </table></div>
            @endif
        </x-filament::section>
    @endunless

    {{ $this->form }}
</x-filament-panels::page>
