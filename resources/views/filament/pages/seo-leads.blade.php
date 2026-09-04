{{--
    Leads-scherm (Groei-meetlaag). Layout-kritische styling staat inline:
    Filament laadt de app-Tailwind niet.
--}}
<x-filament-panels::page>
    @php
        $available = $this->available();
        $stats = $available ? $this->stats() : ['byChannel' => [], 'byType' => [], 'byLandingPath' => [], 'recent' => collect()];
        $typeLabel = fn ($lead) => $lead->typeLabel();
    @endphp

    @unless ($available)
        <div style="border:1px solid rgb(252 211 77);background:rgb(255 251 235);color:rgb(120 53 15);border-radius:.75rem;padding:.75rem 1rem;font-size:.875rem;">
            De leads-tabel bestaat nog niet. Draai <code>php artisan migrate</code> om de meting te starten.
        </div>
    @else
        <x-filament-widgets::widgets :widgets="$this->getWidgets()" :columns="1" />

        {{-- Verdeling laatste 90 dagen --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(16rem,1fr));gap:1rem;">
            @foreach ([['Per kanaal', $stats['byChannel'], 'Waar de bezoeker vandaan kwam bij zijn eerste bezoek.'], ['Per type', $stats['byType'], 'Welke handeling de lead was.'], ['Per landingspagina', $stats['byLandingPath'], 'De pagina waarop de bezoeker binnenkwam — dáár werkt je SEO.']] as [$heading, $rows, $help])
                <x-filament::section :heading="$heading" :description="$help . ' Laatste 90 dagen.'">
                    @if (empty($rows))
                        <div style="font-size:.875rem;color:rgb(107 114 128);">Nog geen leads in deze periode.</div>
                    @else
                        @php $max = max(array_column($rows, 'count')) ?: 1; @endphp
                        <div style="display:flex;flex-direction:column;gap:.5rem;">
                            @foreach ($rows as $row)
                                <div>
                                    <div style="display:flex;justify-content:space-between;gap:.75rem;font-size:.875rem;">
                                        <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $row['label'] }}</span>
                                        <span style="font-weight:600;flex-shrink:0;">{{ $row['count'] }}</span>
                                    </div>
                                    <div style="height:.375rem;border-radius:9999px;background:rgb(229 231 235);overflow:hidden;margin-top:.25rem;">
                                        <div style="height:100%;width:{{ round($row['count'] / $max * 100) }}%;background:rgb(124 58 237);border-radius:9999px;"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-filament::section>
            @endforeach
        </div>

        {{-- Recente leads --}}
        <x-filament::section heading="Recente leads" description="De 50 laatste, nieuwste eerst. Geen berichtinhoud — die staat bij Inzendingen.">
            @if ($stats['recent']->isEmpty())
                <div style="font-size:.875rem;color:rgb(107 114 128);">Nog geen leads gemeten. Zodra iemand een formulier verstuurt, verschijnt hij hier met zijn herkomst.</div>
            @else
                <div style="overflow-x:auto;">
                    <table style="width:100%;font-size:.875rem;border-collapse:collapse;">
                        <thead>
                            <tr style="text-align:left;color:rgb(107 114 128);">
                                <th style="padding:.5rem .75rem;font-weight:500;">Datum</th>
                                <th style="padding:.5rem .75rem;font-weight:500;">Type</th>
                                <th style="padding:.5rem .75rem;font-weight:500;">Kanaal</th>
                                <th style="padding:.5rem .75rem;font-weight:500;">Landingspagina</th>
                                <th style="padding:.5rem .75rem;font-weight:500;">Verwijzer / campagne</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($stats['recent'] as $lead)
                                <tr style="border-top:1px solid rgb(229 231 235);">
                                    <td style="padding:.5rem .75rem;white-space:nowrap;">{{ $lead->created_at->format('d/m/Y H:i') }}</td>
                                    <td style="padding:.5rem .75rem;">{{ $typeLabel($lead) }}</td>
                                    <td style="padding:.5rem .75rem;">{{ \App\Support\Attribution::CHANNEL_LABELS[$lead->channel] ?? ($lead->channel ?: 'Onbekend') }}</td>
                                    <td style="padding:.5rem .75rem;font-family:ui-monospace,monospace;font-size:.8125rem;">{{ $lead->landing_path ?? '—' }}</td>
                                    <td style="padding:.5rem .75rem;color:rgb(107 114 128);">{{ collect([$lead->referrer_host, $lead->utm_campaign])->filter()->implode(' · ') ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    @endunless

    {{ $this->form }}
</x-filament-panels::page>
