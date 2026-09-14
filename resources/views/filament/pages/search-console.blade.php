{{--
    Verkeer — Groei-meetlaag, twee bronnen op één pagina.

    Kerncijfers en verloop staan boven de tabs en blijven dus altijd zichtbaar;
    enkel de detailtabellen zitten per bron achter een tabblad. De blade vraagt
    via $this->tables() enkel de tabellen van het ACTIEVE tabblad op — alles
    tegelijk renderen zou queries kosten voor cijfers die je niet ziet.

    Layout-kritische styling inline: Filament laadt de app-Tailwind niet.
--}}
<x-filament-panels::page>
    @php
        $gsc = app(\App\Services\GoogleSearchConsoleService::class);
        $ga = app(\App\Services\GoogleAnalyticsService::class);
        $ready = $this->tablesReady();
        $hasData = $this->hasData();
        $hasGa = $this->hasAnalyticsData();
        $th = 'padding:.5rem .75rem;font-weight:500;text-align:left;color:rgb(107 114 128);';
        $td = 'padding:.5rem .75rem;border-top:1px solid rgb(229 231 235);';
        $num = $td . 'text-align:right;white-space:nowrap;';
        $tabBase = 'padding:.5rem .9rem;font-size:.875rem;font-weight:500;border-radius:.5rem;cursor:pointer;border:1px solid transparent;background:transparent;color:rgb(107 114 128);';
        $tabOn = $tabBase . 'background:rgb(124 58 237);color:#fff;';
    @endphp

    @unless ($ready)
        <div style="border:1px solid rgb(252 211 77);background:rgb(255 251 235);color:rgb(120 53 15);border-radius:.75rem;padding:.75rem 1rem;font-size:.875rem;">
            De Search Console-tabellen bestaan nog niet. Draai <code>php artisan migrate</code>.
        </div>
    @elseif (! $gsc->hasOAuth() && $gsc->authMethod() === null)
        <div style="border:1px solid rgb(196 181 253);background:rgb(245 243 255);color:rgb(76 29 149);border-radius:.75rem;padding:.75rem 1rem;font-size:.875rem;line-height:1.5;">
            <strong>Nog niet gekoppeld.</strong> Ga naar <a href="{{ \App\Filament\Pages\SeoSettings::getUrl() }}" style="text-decoration:underline;font-weight:600;">SEO-instellingen</a>,
            vul de client-ID en het client-secret in en klik daar op "Verbinden met Google".
            Eén toestemming dekt Search Console én Analytics. Search Console bewaart 16 maanden historiek — na het koppelen staat er meteen een echte trendlijn.
        </div>
    @elseif (! $hasData)
        <div style="border:1px solid rgb(196 181 253);background:rgb(245 243 255);color:rgb(76 29 149);border-radius:.75rem;padding:.75rem 1rem;font-size:.875rem;">
            @if ($gsc->siteUrl !== '')
                Gekoppeld met {{ $gsc->siteUrl }}. Klik bovenaan op "Ververs Google-verkeer" om de eerste 16 maanden in te lezen.
            @else
                Gekoppeld, maar er is nog geen site gekozen. Eén Google-account kan er meerdere beheren: kies de juiste via
                <a href="{{ \App\Filament\Pages\SeoSettings::getUrl() }}" style="text-decoration:underline;font-weight:600;">SEO-instellingen</a> → "Andere site kiezen".
            @endif
        </div>
    @else
        @if ($ga->needsReconsent())
            <div style="border:1px solid rgb(196 181 253);background:rgb(245 243 255);color:rgb(76 29 149);border-radius:.75rem;padding:.75rem 1rem;font-size:.875rem;line-height:1.5;">
                <strong>Analytics hangt er nog niet aan.</strong> Je koppeling dateert van vóór deze uitbreiding en dekt enkel Search Console.
                Klik op <a href="{{ \App\Filament\Pages\SeoSettings::getUrl() }}" style="text-decoration:underline;font-weight:600;">SEO-instellingen</a> op
                <strong>"Analytics mee koppelen"</strong>: Google vraagt dan in één keer beide rechten.
                Je Search Console-cijfers blijven gewoon staan.
            </div>
        @endif

        <x-filament-widgets::widgets :widgets="$this->getWidgets()" :columns="1" />

        {{-- Tabstrip: wisselt enkel de tabellen hieronder, de cijfers blijven staan. --}}
        <div role="tablist" style="display:flex;gap:.25rem;padding:.25rem;border:1px solid rgb(229 231 235);border-radius:.75rem;width:fit-content;">
            <button type="button" role="tab" wire:click="setTab('search')" style="{{ $this->tab === 'search' ? $tabOn : $tabBase }}">
                Uit Google Zoeken
            </button>
            <button type="button" role="tab" wire:click="setTab('site')" style="{{ $this->tab === 'site' ? $tabOn : $tabBase }}">
                Op de site
            </button>
        </div>

        @php $tables = $this->tables(); @endphp

        @if ($this->tab === 'site')
            @if (! $this->analyticsTablesReady())
                <div style="border:1px solid rgb(252 211 77);background:rgb(255 251 235);color:rgb(120 53 15);border-radius:.75rem;padding:.75rem 1rem;font-size:.875rem;">
                    De Analytics-tabellen bestaan nog niet. Draai <code>php artisan migrate</code>.
                </div>
            @elseif (! $hasGa)
                <div style="border:1px solid rgb(196 181 253);background:rgb(245 243 255);color:rgb(76 29 149);border-radius:.75rem;padding:.75rem 1rem;font-size:.875rem;line-height:1.5;">
                    @if ($ga->propertyId === '')
                        <strong>Nog geen Analytics-property gekozen.</strong> Kies er een op
                        <a href="{{ \App\Filament\Pages\SeoSettings::getUrl() }}" style="text-decoration:underline;font-weight:600;">SEO-instellingen</a>.
                    @else
                        <strong>Nog geen Analytics-cijfers.</strong> Klik bovenaan op "Ververs Analytics".
                        Staat de meetcode al op de site? Analytics toont niets van vóór de dag dat het script begon te meten.
                    @endif
                </div>
            @else
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(22rem,1fr));gap:1rem;">
                    <x-filament::section heading="Meest bekeken pagina's" description="Waar mensen hun tijd doorbrengen — laatste 28 dagen.">
                        @if (empty($tables['pages']))
                            <div style="font-size:.875rem;color:rgb(107 114 128);">Nog geen pagina's in deze periode.</div>
                        @else
                            <div style="overflow-x:auto;"><table style="width:100%;font-size:.875rem;border-collapse:collapse;">
                                <thead><tr><th style="{{ $th }}">Pagina</th><th style="{{ $th }}text-align:right;">Weerg.</th><th style="{{ $th }}text-align:right;">Sessies</th><th style="{{ $th }}text-align:right;">Tijd</th></tr></thead>
                                <tbody>
                                    @foreach ($tables['pages'] as $row)
                                        <tr>
                                            <td style="{{ $td }}font-family:ui-monospace,monospace;font-size:.8125rem;word-break:break-all;">{{ $row['value'] }}</td>
                                            <td style="{{ $num }}">{{ number_format($row['page_views'], 0, ',', '.') }}</td>
                                            <td style="{{ $num }}">{{ number_format($row['sessions'], 0, ',', '.') }}</td>
                                            <td style="{{ $num }}">{{ $row['avg_session_seconds'] < 60 ? $row['avg_session_seconds'] . ' s' : intdiv($row['avg_session_seconds'], 60) . ' m ' . ($row['avg_session_seconds'] % 60) . ' s' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table></div>
                        @endif
                    </x-filament::section>

                    <x-filament::section heading="Kanalen" description="Waarlangs het volk binnenkomt. Alles buiten Google Zoeken is onzichtbaar in het andere tabblad.">
                        @if (empty($tables['channels']))
                            <div style="font-size:.875rem;color:rgb(107 114 128);">Nog geen kanalen in deze periode.</div>
                        @else
                            <div style="overflow-x:auto;"><table style="width:100%;font-size:.875rem;border-collapse:collapse;">
                                <thead><tr><th style="{{ $th }}">Kanaal</th><th style="{{ $th }}text-align:right;">Sessies</th><th style="{{ $th }}text-align:right;">Aandeel</th></tr></thead>
                                <tbody>
                                    @foreach ($tables['channels'] as $row)
                                        <tr>
                                            <td style="{{ $td }}">{{ $row['value'] }}</td>
                                            <td style="{{ $num }}">{{ number_format($row['sessions'], 0, ',', '.') }}</td>
                                            <td style="{{ $num }}">{{ number_format($row['share'], 1, ',', '.') }} %</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table></div>
                        @endif
                    </x-filament::section>
                </div>
            @endif
        @else
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
        @endif
    @endunless

</x-filament-panels::page>
