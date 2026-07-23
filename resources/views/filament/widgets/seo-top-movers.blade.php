{{--
    Stijgers en dalers naast elkaar. Layout-kritische styling staat inline:
    de app-Tailwind wordt niet in het Filament-panel geladen, dus utility-
    klassen uit resources/css/app.css zijn hier niet beschikbaar.
--}}
<x-filament-widgets::widget>
    <x-filament::section :heading="'Grootste bewegingen'">
        @php
            $columns = [
                ['title' => 'Gestegen', 'items' => $up, 'color' => '#16a34a', 'empty' => 'Geen stijgers deze meting.'],
                ['title' => 'Gedaald', 'items' => $down, 'color' => '#dc2626', 'empty' => 'Geen dalers deze meting.'],
            ];
        @endphp

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem;">
            @foreach ($columns as $column)
                <div>
                    <div style="font-weight: 600; margin-bottom: 0.5rem;" class="text-gray-950 dark:text-white">
                        {{ $column['title'] }}
                    </div>

                    @forelse ($column['items'] as $item)
                        <div style="display: flex; align-items: baseline; justify-content: space-between; gap: 0.75rem; padding: 0.375rem 0;"
                             class="border-b border-gray-100 dark:border-white/10">
                            <span style="min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"
                                  class="text-gray-700 dark:text-gray-300"
                                  title="{{ $item['keyword'] }}">
                                {{ $item['keyword'] }}
                            </span>
                            <span style="white-space: nowrap; font-variant-numeric: tabular-nums;">
                                <span class="text-gray-500 dark:text-gray-400">#{{ $item['rank'] }}</span>
                                <span style="color: {{ $column['color'] }}; font-weight: 600; margin-left: 0.375rem;">
                                    {{ $item['delta'] > 0 ? '+' : '' }}{{ $item['delta'] }}
                                </span>
                            </span>
                        </div>
                    @empty
                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ $column['empty'] }}</div>
                    @endforelse
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
