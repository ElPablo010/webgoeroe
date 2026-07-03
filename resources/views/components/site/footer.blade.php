@php
    $footer = \App\Support\SiteFooter::current();
    $contact = $footer['contact'] ?? [];
    $brand = $footer['brand'] ?? [];
    $social = $footer['social'] ?? [];
    $footerMenus = \App\Models\Menu::whereIn('location', ['footer_1', 'footer_2', 'footer_3'])
        ->with('items')
        ->get()
        ->keyBy('location');
@endphp

<footer class="border-t border-white/[0.06] bg-[#050507] text-white/50">
    <div class="mx-auto max-w-6xl px-6 py-14">
        <div class="grid gap-10 sm:grid-cols-2 md:grid-cols-4">

            {{-- Brand + social --}}
            <div class="sm:col-span-2 md:col-span-1">
                <div class="mb-1 flex items-center gap-2">
                    <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br from-cyan-400 to-primary-600 text-xs font-black text-white">W</span>
                    <span class="text-base font-bold text-white">{{ $brand['name'] ?? config('app.name') }}</span>
                </div>
                @if (! empty($brand['tagline']))
                    <p class="mt-3 text-sm leading-relaxed text-white/40">{{ $brand['tagline'] }}</p>
                @endif

                @if (! empty($social['facebook']) || ! empty($social['instagram']) || ! empty($social['youtube']))
                    <div class="mt-5 flex gap-3">
                        @if (! empty($social['facebook']))
                            <a href="{{ $social['facebook'] }}" target="_blank" rel="noopener" class="text-white/30 transition-colors hover:text-white" aria-label="Facebook">
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M22 12C22 6.477 17.523 2 12 2S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.879V14.89h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.989C18.343 21.129 22 16.99 22 12z"/></svg>
                            </a>
                        @endif
                        @if (! empty($social['instagram']))
                            <a href="{{ $social['instagram'] }}" target="_blank" rel="noopener" class="text-white/30 transition-colors hover:text-white" aria-label="Instagram">
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                            </a>
                        @endif
                        @if (! empty($social['youtube']))
                            <a href="{{ $social['youtube'] }}" target="_blank" rel="noopener" class="text-white/30 transition-colors hover:text-white" aria-label="YouTube">
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                            </a>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Footer menus --}}
            @foreach (['footer_1', 'footer_2'] as $location)
                @php $fMenu = $footerMenus->get($location); @endphp
                @if ($fMenu && $fMenu->items->isNotEmpty())
                    <div>
                        @if (! empty($fMenu->title))
                            <div class="mb-4 text-xs font-semibold uppercase tracking-wider text-white/30">{{ $fMenu->title }}</div>
                        @endif
                        <ul class="space-y-2.5 text-sm">
                            @foreach ($fMenu->items as $item)
                                <li>
                                    <a
                                        href="{{ $item->resolvedHref() }}"
                                        @if ($item->target_blank) target="_blank" rel="noopener" @endif
                                        class="text-white/40 transition-colors hover:text-white"
                                    >{{ $item->label }}</a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @else
                    <div></div>
                @endif
            @endforeach

            {{-- Contact --}}
            @if (! empty($contact['email']) || ! empty($contact['phone']) || ! empty($contact['address']))
                <div>
                    <div class="mb-4 text-xs font-semibold uppercase tracking-wider text-white/30">Contact</div>
                    <ul class="space-y-2.5 text-sm">
                        @if (! empty($contact['email']))
                            <li>
                                <a href="mailto:{{ $contact['email'] }}" class="flex items-center gap-2 text-white/40 transition-colors hover:text-white">
                                    <x-lucide-mail class="h-4 w-4 shrink-0" />
                                    {{ $contact['email'] }}
                                </a>
                            </li>
                        @endif
                        @if (! empty($contact['phone']))
                            <li>
                                <a href="tel:{{ preg_replace('/[^0-9+]/', '', $contact['phone']) }}" class="flex items-center gap-2 text-white/40 transition-colors hover:text-white">
                                    <x-lucide-phone class="h-4 w-4 shrink-0" />
                                    {{ $contact['phone'] }}
                                </a>
                            </li>
                        @endif
                        @if (! empty($contact['address']))
                            <li class="flex items-start gap-2 text-white/40">
                                <x-lucide-map-pin class="mt-0.5 h-4 w-4 shrink-0" />
                                <span>{{ $contact['address'] }}</span>
                            </li>
                        @endif
                    </ul>
                </div>
            @else
                <div></div>
            @endif
        </div>

        <div class="mt-12 flex flex-col items-center gap-4 border-t border-white/[0.06] pt-6 text-xs text-white/20 sm:flex-row sm:justify-between">
            <span>© {{ now()->year }} {{ $brand['name'] ?? config('app.name') }} — Alle rechten voorbehouden.</span>
            <button
                type="button"
                onclick="window.dispatchEvent(new CustomEvent('open-cookie-preferences'))"
                class="flex cursor-pointer items-center gap-1.5 text-white/20 transition-colors hover:text-white/50"
            >
                <x-lucide-settings class="h-3.5 w-3.5" />
                Cookie-instellingen
            </button>
        </div>
    </div>
</footer>
