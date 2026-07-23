<x-filament-panels::page>
    @php
        $counts = $this->counts();
        $items = $this->items();
        $domain = app(\App\Services\DataForSeoService::class)->target;
        $typeLabels = ['create_page' => 'Nieuwe pagina', 'add_section' => 'FAQ toevoegen', 'optimize_meta' => 'Meta optimaliseren'];
        $prioLabels = ['high' => 'Hoge impact', 'medium' => 'Middelmatige impact', 'low' => 'Lage impact'];
        $statusBadge = ['pending' => ['Te beoordelen', 'warning'], 'published' => ['Gepubliceerd', 'success'], 'dismissed' => ['Genegeerd', 'gray']];
        $filters = ['all' => 'Alle', 'pending' => 'Te beoordelen', 'published' => 'Goedgekeurd', 'dismissed' => 'Genegeerd'];
    @endphp

    {{-- Filter --}}
    <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
        @foreach ($filters as $key => $label)
            <x-filament::button
                wire:click="setFilter('{{ $key }}')"
                :color="$filter === $key ? 'primary' : 'gray'"
                size="sm"
            >
                {{ $label }} ({{ $counts[$key] ?? 0 }})
            </x-filament::button>
        @endforeach
    </div>

    @forelse ($items as $item)
        @php
            [$statusText, $statusColor] = $statusBadge[$item['status']] ?? ['—', 'gray'];
            $proposed = $item['proposed'] ?? [];
            $sections = $proposed['sections'] ?? [];
            $text = collect($sections)->firstWhere('section_type', 'text');
            $faqSection = collect($sections)->firstWhere('section_type', 'faq');
            $faqItems = data_get($faqSection, 'content.items', data_get($proposed, 'content.items', []));
            $metaTitle = $proposed['meta_title'] ?? (data_get($text, 'content.heading') ?: $item['title']);
            $metaDesc = $proposed['meta_description'] ?? null;
            $slug = $item['action_type'] === 'create_page'
                ? ($proposed['slug'] ?? '')
                : ($item['page']['slug'] ?? '');
            $slug = ltrim((string) $slug, '/');
            $showSerp = in_array($item['action_type'], ['create_page', 'optimize_meta'], true);
        @endphp

        <x-filament::section>
            <x-slot name="heading">
                <div style="display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
                    <x-filament::badge color="primary">{{ $typeLabels[$item['action_type']] ?? $item['action_type'] }}</x-filament::badge>
                    <x-filament::badge color="gray">{{ $prioLabels[$item['priority']] ?? $item['priority'] }}</x-filament::badge>
                    <x-filament::badge :color="$statusColor">{{ $statusText }}</x-filament::badge>
                    @if ($item['source_keyword'])
                        <span style="font-size:.75rem;opacity:.6;">keyword: “{{ $item['source_keyword'] }}”</span>
                    @endif
                </div>
            </x-slot>

            {{-- Probleem --}}
            <div style="margin-bottom:1rem;">
                <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;font-weight:700;opacity:.5;margin-bottom:.25rem;">Probleem</div>
                <p style="opacity:.8;">{{ $item['problem'] }}</p>
            </div>

            @if ($editingId === $item['id'])
                {{-- Inline editor --}}
                <div style="display:flex;flex-direction:column;gap:.75rem;">
                    <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;font-weight:700;opacity:.5;">Bewerk vóór publiceren</div>

                    @if (in_array($item['action_type'], ['create_page', 'optimize_meta'], true))
                        <label style="font-size:.8rem;font-weight:600;">Meta title
                            <input type="text" wire:model="editForm.meta_title" style="width:100%;padding:.5rem;border:1px solid rgba(128,128,128,.4);border-radius:.5rem;background:transparent;color:inherit;" />
                        </label>
                        <label style="font-size:.8rem;font-weight:600;">Meta description
                            <textarea wire:model="editForm.meta_description" rows="2" style="width:100%;padding:.5rem;border:1px solid rgba(128,128,128,.4);border-radius:.5rem;background:transparent;color:inherit;"></textarea>
                        </label>
                    @endif

                    @if ($item['action_type'] === 'create_page')
                        <label style="font-size:.8rem;font-weight:600;">Titel (H1)
                            <input type="text" wire:model="editForm.heading" style="width:100%;padding:.5rem;border:1px solid rgba(128,128,128,.4);border-radius:.5rem;background:transparent;color:inherit;" />
                        </label>
                        <label style="font-size:.8rem;font-weight:600;">Introtekst (HTML toegestaan)
                            <textarea wire:model="editForm.body" rows="4" style="width:100%;padding:.5rem;border:1px solid rgba(128,128,128,.4);border-radius:.5rem;background:transparent;color:inherit;"></textarea>
                        </label>
                    @endif

                    @if (in_array($item['action_type'], ['create_page', 'add_section'], true))
                        <div style="font-size:.8rem;font-weight:600;">FAQ</div>
                        @foreach ($editForm['faq'] ?? [] as $i => $row)
                            <div style="border:1px solid rgba(128,128,128,.3);border-radius:.5rem;padding:.5rem;display:flex;flex-direction:column;gap:.35rem;">
                                <input type="text" wire:model="editForm.faq.{{ $i }}.question" placeholder="Vraag" style="width:100%;padding:.4rem;border:1px solid rgba(128,128,128,.4);border-radius:.4rem;background:transparent;color:inherit;" />
                                <textarea wire:model="editForm.faq.{{ $i }}.answer" rows="2" placeholder="Antwoord" style="width:100%;padding:.4rem;border:1px solid rgba(128,128,128,.4);border-radius:.4rem;background:transparent;color:inherit;"></textarea>
                                <button type="button" wire:click="removeFaqRow({{ $i }})" style="font-size:.75rem;opacity:.6;text-align:left;cursor:pointer;background:none;border:none;color:inherit;">Verwijderen</button>
                            </div>
                        @endforeach
                        <button type="button" wire:click="addFaqRow" style="font-size:.8rem;font-weight:600;text-align:left;cursor:pointer;background:none;border:none;color:inherit;opacity:.8;">+ Vraag toevoegen</button>
                    @endif

                    <div style="display:flex;gap:.5rem;">
                        <x-filament::button wire:click="publish({{ $item['id'] }})" color="primary">Publiceren</x-filament::button>
                        <x-filament::button wire:click="cancelEdit" color="gray">Annuleren</x-filament::button>
                    </div>
                </div>
            @else
                {{-- Voorgestelde oplossing --}}
                <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;font-weight:700;opacity:.5;margin-bottom:.25rem;">Voorgestelde oplossing</div>
                <div style="font-weight:600;margin-bottom:.5rem;">{{ $item['title'] }}</div>

                @if ($showSerp && ($metaTitle || $metaDesc))
                    <div style="border:1px solid rgba(128,128,128,.25);border-radius:.5rem;padding:.6rem .75rem;margin-bottom:.75rem;">
                        <div style="font-size:.75rem;color:#15803d;">{{ $domain }}{{ $slug ? ' › ' . $slug : '' }}</div>
                        <div style="font-size:.95rem;color:#1d4ed8;">{{ $metaTitle }}</div>
                        @if ($metaDesc)
                            <div style="font-size:.8rem;opacity:.7;margin-top:.15rem;">{{ $metaDesc }}</div>
                        @endif
                    </div>
                @endif

                @if ($text)
                    <div style="margin-bottom:.75rem;">
                        @if (! empty($text['content']['heading']))
                            <div style="font-weight:600;">{{ $text['content']['heading'] }}</div>
                        @endif
                        @if (! empty($text['content']['body']))
                            <div style="opacity:.75;font-size:.9rem;">{!! $text['content']['body'] !!}</div>
                        @endif
                    </div>
                @endif

                @if (! empty($faqItems))
                    <div style="border:1px solid rgba(128,128,128,.25);border-radius:.5rem;overflow:hidden;margin-bottom:.25rem;">
                        @foreach ($faqItems as $f)
                            <div style="padding:.5rem .75rem;border-top:1px solid rgba(128,128,128,.2);">
                                <div style="font-size:.9rem;font-weight:600;">{{ $f['question'] ?? '' }}</div>
                                <div style="font-size:.9rem;opacity:.7;">{!! $f['answer'] ?? '' !!}</div>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- Acties --}}
                <div style="margin-top:1rem;padding-top:.75rem;border-top:1px solid rgba(128,128,128,.2);display:flex;align-items:center;gap:.5rem;flex-wrap:wrap;">
                    @if ($item['status'] === 'pending')
                        <x-filament::button wire:click="approve({{ $item['id'] }})" color="primary" icon="heroicon-o-check">Goedkeuren &amp; publiceren</x-filament::button>
                        <x-filament::button wire:click="startEdit({{ $item['id'] }})" color="gray">Aanpassen</x-filament::button>
                        <x-filament::button wire:click="dismiss({{ $item['id'] }})" color="gray" size="sm">Negeren</x-filament::button>
                    @elseif ($item['status'] === 'published')
                        @if ($item['result_url'])
                            <a href="{{ $item['result_url'] }}" target="_blank" style="font-size:.85rem;font-weight:600;color:#1d4ed8;">Bekijk pagina ↗</a>
                        @endif
                        @if ($item['feedback'])
                            <div style="width:100%;font-size:.75rem;opacity:.7;margin-top:.25rem;">
                                @if ($item['feedback']['measured'] ?? false)
                                    Opvolging “{{ $item['source_keyword'] }}”:
                                    <strong>{{ $item['feedback']['rank'] ? '#' . $item['feedback']['rank'] : 'niet in top 100' }}</strong>
                                    @if (! empty($item['feedback']['delta']))
                                        ({{ $item['feedback']['delta'] > 0 ? '▲ +' . $item['feedback']['delta'] : '▼ ' . $item['feedback']['delta'] }})
                                    @endif
                                    @if ($item['feedback']['ai_cited'] ?? false) · geciteerd in AI @endif
                                    · gemeten {{ $item['feedback']['checked_at'] }}
                                @elseif ($item['feedback']['tracked'] ?? false)
                                    “{{ $item['source_keyword'] }}” staat nu in de opvolging — positie verschijnt na de volgende verversing.
                                @endif
                            </div>
                        @endif
                    @else
                        <span style="font-size:.85rem;opacity:.7;">Genegeerd</span>
                        <x-filament::button wire:click="restore({{ $item['id'] }})" color="gray" size="sm">Terugzetten</x-filament::button>
                    @endif
                </div>
            @endif
        </x-filament::section>
    @empty
        <x-filament::section>
            <div style="text-align:center;padding:2rem;opacity:.7;">
                <p style="margin-bottom:1rem;">Nog geen verbeteracties. Ze verschijnen automatisch na de wekelijkse SEO-analyse — of genereer ze nu meteen uit de laatste data.</p>
                <x-filament::button wire:click="generateNow" color="primary" icon="heroicon-o-sparkles">Genereer je eerste acties</x-filament::button>
            </div>
        </x-filament::section>
    @endforelse
</x-filament-panels::page>
