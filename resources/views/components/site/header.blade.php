@php
    $header = \App\Support\SiteHeader::current();
    $menu = \App\Models\Menu::where('location', 'main')->with('items.children')->first();
    $cta = $header['cta'] ?? [];
@endphp

{{--
    Header: altijd liquid-glass (background:#ffffff08, backdrop-filter:blur(20px) saturate(180%))
    met een subtiele gradient-border boven- en onderkant. Exact leadexpert.be-stijl.
--}}
<header
    x-data="{ open: false }"
    class="fixed left-1/2 top-4 z-50 w-[calc(100%-2rem)] max-w-5xl -translate-x-1/2"
    style="background:rgba(255,255,255,0.04); backdrop-filter:blur(20px) saturate(180%); -webkit-backdrop-filter:blur(20px) saturate(180%); border:1px solid rgba(255,255,255,0.08); border-radius:16px;"
>
    <div class="flex items-center justify-between gap-6 px-5 py-3.5">

        {{-- Logo --}}
        <a href="/" class="flex items-center gap-2.5 shrink-0">
            @if (! empty($header['logo']))
                <img src="{{ $header['logo'] }}" alt="{{ $header['name'] }}" class="h-8 w-auto">
            @else
                <span
                    class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-sm font-black text-white"
                    style="background:linear-gradient(135deg,#22d3ee,#7c3aed); box-shadow:0 0 16px rgba(124,58,237,0.4);"
                >W</span>
            @endif
            <span class="text-base font-bold text-white">{{ $header['name'] }}</span>
        </a>

        {{-- Desktop nav --}}
        @if ($menu)
            <nav class="hidden items-center gap-1 md:flex">
                @foreach ($menu->items as $item)
                    @if ($item->children->isNotEmpty())
                        <div
                            class="relative"
                            x-data="{ sub: false }"
                            @click.outside="sub = false"
                        >
                            <button class="flex cursor-pointer items-center gap-1 rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                                @click="sub = !sub"
                                style="color:rgba(255,255,255,0.7);"
                                onmouseenter="this.style.color='#fff'; this.style.background='rgba(255,255,255,0.06)'"
                                onmouseleave="this.style.color='rgba(255,255,255,0.7)'; this.style.background=''"
                            >
                                {{ $item->label }}
                                <svg class="h-3.5 w-3.5 opacity-50 transition-transform" :class="sub ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            <div
                                x-show="sub"
                                x-cloak
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 -translate-y-1"
                                x-transition:enter-end="opacity-100 translate-y-0"
                                class="absolute left-0 top-full z-50 mt-2 min-w-[200px] overflow-hidden rounded-xl py-1.5 shadow-2xl"
                                style="background:#0c0c10; border:1px solid rgba(255,255,255,0.08); backdrop-filter:blur(20px);"
                            >
                                @foreach ($item->children as $child)
                                    <a
                                        href="{{ $child->resolvedHref() }}"
                                        @if ($child->target_blank) target="_blank" rel="noopener" @endif
                                        class="block px-4 py-2.5 text-sm transition-colors"
                                        style="color:rgba(255,255,255,0.6);"
                                        onmouseenter="this.style.color='#fff'; this.style.background='rgba(255,255,255,0.05)'"
                                        onmouseleave="this.style.color='rgba(255,255,255,0.6)'; this.style.background=''"
                                    >{{ $child->label }}</a>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <a
                            href="{{ $item->resolvedHref() }}"
                            @if ($item->target_blank) target="_blank" rel="noopener" @endif
                            class="rounded-lg px-3 py-2 text-sm font-medium transition-colors"
                            style="color:rgba(255,255,255,0.7);"
                            onmouseenter="this.style.color='#fff'; this.style.background='rgba(255,255,255,0.06)'"
                            onmouseleave="this.style.color='rgba(255,255,255,0.7)'; this.style.background=''"
                        >{{ $item->label }}</a>
                    @endif
                @endforeach
            </nav>
        @endif

        <div class="flex items-center gap-3">
            @if (! empty($cta['label']))
                {{-- btn-primary: exact leadexpert stijl --}}
                <a
                    href="{{ $cta['href'] ?? '/' }}"
                    class="hidden cursor-pointer px-5 py-2.5 text-sm font-semibold transition-all md:inline-flex"
                    style="background:#fff; color:#000; border-radius:100px; box-shadow:0 0 rgba(255,255,255,0);"
                    onmouseenter="this.style.background='rgba(255,255,255,0.92)'; this.style.boxShadow='0 0 40px rgba(255,255,255,0.2),0 8px 30px rgba(0,0,0,0.4)'; this.style.transform='translateY(-1px)'"
                    onmouseleave="this.style.background='#fff'; this.style.boxShadow='0 0 rgba(255,255,255,0)'; this.style.transform=''"
                >{{ $cta['label'] }}</a>
            @endif

            {{-- Mobile hamburger --}}
            <button
                @click="open = !open"
                class="cursor-pointer rounded-lg p-2 transition-colors md:hidden"
                style="color:rgba(255,255,255,0.6);"
                onmouseenter="this.style.background='rgba(255,255,255,0.06)'; this.style.color='#fff'"
                onmouseleave="this.style.background=''; this.style.color='rgba(255,255,255,0.6)'"
                aria-label="Menu"
            >
                <svg x-show="!open" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
                <svg x-show="open" x-cloak class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- Mobile menu --}}
    <div
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        style="border-top:1px solid rgba(255,255,255,0.06);"
        class="md:hidden"
    >
        <div class="space-y-1 px-5 py-3">
            @if ($menu)
                @foreach ($menu->items as $item)
                    <a
                        href="{{ $item->resolvedHref() }}"
                        @if ($item->target_blank) target="_blank" rel="noopener" @endif
                        class="block rounded-lg px-3 py-2.5 text-sm font-medium transition-colors"
                        style="color:rgba(255,255,255,0.7);"
                        @click="open = false"
                    >{{ $item->label }}</a>
                    @foreach ($item->children as $child)
                        <a
                            href="{{ $child->resolvedHref() }}"
                            @if ($child->target_blank) target="_blank" rel="noopener" @endif
                            class="block rounded-lg py-2 pl-7 text-sm transition-colors"
                            style="color:rgba(255,255,255,0.4);"
                            @click="open = false"
                        >{{ $child->label }}</a>
                    @endforeach
                @endforeach
            @endif
            @if (! empty($cta['label']))
                <div class="pt-2">
                    <a
                        href="{{ $cta['href'] ?? '/' }}"
                        class="block cursor-pointer px-5 py-2.5 text-center text-sm font-semibold"
                        style="background:#fff; color:#000; border-radius:100px;"
                    >{{ $cta['label'] }}</a>
                </div>
            @endif
        </div>
    </div>
</header>

