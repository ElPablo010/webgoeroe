<x-layouts.site
    :title="$seo['title']"
    :description="$seo['description']"
    :canonical="$seo['canonical']"
    :robots="$seo['robots']"
    :image="$seo['image']"
    :image-alt="$seo['imageAlt']"
    :image-width="$seo['imageWidth']"
    :image-height="$seo['imageHeight']"
    :type="$seo['type']"
    :schema="$seo['schema']"
    :page="null"
>
    @php $c = $case->content ?? []; @endphp

    @auth
        <a
            href="{{ route('filament.admin.resources.cases.edit', ['record' => $case, 'tab' => 'sections']) }}"
            class="fixed right-4 top-20 z-50 flex h-10 w-10 items-center justify-center rounded-full bg-violet-600 text-white shadow-lg transition hover:bg-violet-700"
            title="Bewerk deze case study"
            aria-label="Bewerk case study"
        >
            <x-lucide-square-pen class="h-4 w-4" />
        </a>
    @endauth

    {{-- ── HERO ──────────────────────────────────────────────────── --}}
    <section class="relative overflow-hidden bg-[#050507] pt-10 md:pt-16 lg:pt-20">
        <div
            class="absolute inset-0"
            style="background-image: linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px); background-size: 60px 60px;"
        ></div>
        <div class="pointer-events-none absolute -right-48 -top-48 h-[600px] w-[600px] animate-glow-drift rounded-full bg-cyan-400/[0.08] blur-[120px]"></div>
        <div class="pointer-events-none absolute -bottom-32 -left-48 h-[500px] w-[500px] animate-glow-drift-alt rounded-full bg-primary-600/[0.10] blur-[120px]"></div>

        <div class="relative mx-auto max-w-4xl px-6 pb-16 pt-8">

            {{-- Terug-link --}}
            <a
                href="{{ route('case-studies.index') }}"
                class="mb-8 inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-white/40 transition-colors hover:text-white/70"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
                Alle case studies
            </a>

            {{-- Tags --}}
            @if (! empty($case->tags))
                <div class="mb-5 flex flex-wrap gap-2">
                    @foreach ($case->tags as $tag)
                        <span class="rounded-full border border-white/[0.12] bg-white/[0.06] px-3 py-1 text-xs font-medium text-white/70">
                            {{ $tag }}
                        </span>
                    @endforeach
                </div>
            @endif

            {{-- Titel --}}
            <h1 class="text-4xl font-black leading-[1.08] tracking-[-0.02em] text-white md:text-5xl lg:text-6xl">
                {{ $case->title }}
            </h1>

            {{-- Excerpt --}}
            @if ($case->excerpt)
                <p class="mt-5 max-w-2xl text-lg leading-relaxed text-white/50">
                    {{ $case->excerpt }}
                </p>
            @endif

            {{-- Meta-balk --}}
            @if ($case->client || $case->industry)
                <div class="mt-8 flex flex-wrap gap-6 border-t border-white/[0.08] pt-6 text-sm text-white/40">
                    @if ($case->client)
                        <div>
                            <span class="block text-xs font-semibold uppercase tracking-wider text-white/25">Klant</span>
                            <span class="mt-0.5 block font-medium text-white/60">{{ $case->client }}</span>
                        </div>
                    @endif
                    @if ($case->industry)
                        <div>
                            <span class="block text-xs font-semibold uppercase tracking-wider text-white/25">Sector</span>
                            <span class="mt-0.5 block font-medium text-white/60">{{ $case->industry }}</span>
                        </div>
                    @endif
                </div>
            @endif
        </div>

        {{-- Cover-afbeelding --}}
        @if ($case->cover_url)
            <div class="relative mx-auto max-w-5xl px-6 pb-0">
                <div class="overflow-hidden rounded-t-2xl border border-b-0 border-white/[0.08]">
                    <picture>
                        <source srcset="{{ $case->cover_url }}" type="image/webp">
                        <img
                            src="{{ $case->cover_url }}"
                            alt="{{ $case->cover_alt ?: $case->title }}"
                            class="w-full origin-center animate-image-zoom-out object-cover"
                            fetchpriority="high"
                        >
                    </picture>
                </div>
            </div>
        @endif
    </section>

    {{-- ── UITDAGING ─────────────────────────────────────────────── --}}
    @if (! empty($c['challenge']['body']))
        <section class="bg-[#050507] py-20 md:py-28">
            <div data-reveal class="mx-auto max-w-3xl px-6">
                <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-primary-400">De uitdaging</p>
                <h2 class="text-3xl font-black tracking-tight text-white md:text-4xl">Waar stonden we?</h2>
                <div class="prose prose-invert mt-6 max-w-none prose-p:text-white/60 prose-p:leading-relaxed">
                    {!! nl2br(e($c['challenge']['body'])) !!}
                </div>
            </div>
        </section>
    @endif

    {{-- ── PROJECTDOELEN ──────────────────────────────────────────── --}}
    @if (! empty($c['goals']))
        <section class="bg-[#0c0c10] py-20 md:py-28">
            <div class="mx-auto max-w-3xl px-6">
                <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-primary-400">Projectdoelen</p>
                <h2 class="text-3xl font-black tracking-tight text-white md:text-4xl">Wat wilden we bereiken?</h2>
                <ol class="mt-8 space-y-4">
                    @foreach ($c['goals'] as $i => $goal)
                        @if (! empty($goal['text']))
                            <li data-reveal style="animation-delay: {{ min($i, 5) * 70 }}ms" class="flex items-start gap-4">
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-600/20 text-sm font-bold text-primary-400 ring-1 ring-primary-500/30">
                                    {{ $i + 1 }}
                                </span>
                                <span class="pt-1 text-base leading-relaxed text-white/70">{{ $goal['text'] }}</span>
                            </li>
                        @endif
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    {{-- ── AANPAK ─────────────────────────────────────────────────── --}}
    @if (! empty($c['approach']['steps']))
        <section class="bg-[#050507] py-20 md:py-28">
            <div class="mx-auto max-w-3xl px-6">
                <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-primary-400">Onze aanpak</p>
                <h2 class="text-3xl font-black tracking-tight text-white md:text-4xl">Hoe hebben we dit aangepakt?</h2>
                <div class="mt-10 space-y-10">
                    @foreach ($c['approach']['steps'] as $index => $step)
                        @if (! empty($step['title']))
                            <div data-reveal style="animation-delay: {{ min($index, 5) * 90 }}ms" class="relative pl-8">
                                <div class="absolute left-0 top-1.5 h-3 w-3 rounded-full bg-primary-500 ring-4 ring-primary-500/20"></div>
                                @if (! $loop->last)
                                    <div class="absolute left-[5px] top-5 h-full w-px bg-white/[0.06]"></div>
                                @endif
                                <h3 class="text-lg font-bold text-white">{{ $step['title'] }}</h3>
                                @if (! empty($step['body']))
                                    <p class="mt-2 leading-relaxed text-white/55">{{ $step['body'] }}</p>
                                @endif
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ── OPLOSSING ──────────────────────────────────────────────── --}}
    @if (! empty($c['solution']['body']))
        <section class="bg-[#0c0c10] py-20 md:py-28">
            <div data-reveal class="mx-auto max-w-3xl px-6">
                <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-primary-400">De oplossing</p>
                <h2 class="text-3xl font-black tracking-tight text-white md:text-4xl">Wat hebben we gebouwd?</h2>
                <div class="prose prose-invert mt-6 max-w-none prose-p:text-white/60 prose-p:leading-relaxed">
                    {!! nl2br(e($c['solution']['body'])) !!}
                </div>

                @if (! empty($c['solution']['image_url']))
                    <div data-reveal-scale class="mt-10 overflow-hidden rounded-2xl border border-white/[0.08] shadow-2xl">
                        <picture>
                            <source srcset="{{ $c['solution']['image_url'] }}" type="image/webp">
                            <img
                                src="{{ $c['solution']['image_url'] }}"
                                alt="{{ $c['solution']['image_alt'] ?? '' }}"
                                class="w-full"
                                loading="lazy"
                            >
                        </picture>
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- ── RESULTAAT ──────────────────────────────────────────────── --}}
    @if (! empty($c['results']['metrics']) || ! empty($c['results']['intro']))
        <section class="bg-[#050507] py-20 md:py-28">
            <div class="mx-auto max-w-3xl px-6">
                <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-primary-400">Het resultaat</p>
                <h2 class="text-3xl font-black tracking-tight text-white md:text-4xl">Wat heeft het opgeleverd?</h2>

                @if (! empty($c['results']['intro']))
                    <p class="mt-5 leading-relaxed text-white/55">{{ $c['results']['intro'] }}</p>
                @endif

                @if (! empty($c['results']['metrics']))
                    <div class="mt-10 grid grid-cols-2 gap-4 sm:grid-cols-2">
                        @foreach ($c['results']['metrics'] as $index => $metric)
                            @if (! empty($metric['label']) && ! empty($metric['value']))
                                <div data-reveal style="animation-delay: {{ min($index, 5) * 80 }}ms" class="rounded-xl border border-white/[0.08] bg-white/[0.03] p-5">
                                    <div data-count-up class="text-2xl font-black text-white">{{ $metric['value'] }}</div>
                                    <div class="mt-1 text-sm text-white/50">{{ $metric['label'] }}</div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    @endif

    {{-- ── GETUIGENIS ─────────────────────────────────────────────── --}}
    @if (! empty($c['testimonial']['quote']))
        <section class="relative overflow-hidden py-20 md:py-28" style="background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #164e63 100%);">
            <div class="pointer-events-none absolute inset-0" style="background: radial-gradient(ellipse 60% 50% at 50% 0%, rgba(124,58,237,0.08) 0%, transparent 70%);"></div>
            <div data-reveal-scale class="relative mx-auto max-w-3xl px-6 text-center">
                <svg class="mx-auto mb-6 h-10 w-10 text-white/20" fill="currentColor" viewBox="0 0 32 32">
                    <path d="M9.352 4C4.456 7.456 1 13.12 1 19.36c0 5.088 3.072 8.064 6.624 8.064 3.36 0 5.856-2.688 5.856-5.856 0-3.168-2.208-5.472-5.088-5.472-.576 0-1.344.096-1.536.192.48-3.264 3.552-7.104 6.624-9.024L9.352 4zm16.512 0c-4.8 3.456-8.256 9.12-8.256 15.36 0 5.088 3.072 8.064 6.624 8.064 3.264 0 5.856-2.688 5.856-5.856 0-3.168-2.304-5.472-5.184-5.472-.576 0-1.248.096-1.44.192.48-3.264 3.456-7.104 6.528-9.024L25.864 4z"/>
                </svg>
                <blockquote class="text-xl font-medium leading-relaxed text-white md:text-2xl">
                    "{{ $c['testimonial']['quote'] }}"
                </blockquote>
                <div class="mt-8 flex items-center justify-center gap-4">
                    @if (! empty($c['testimonial']['avatar_url']))
                        <picture>
                            <source srcset="{{ $c['testimonial']['avatar_url'] }}" type="image/webp">
                            <img
                                src="{{ $c['testimonial']['avatar_url'] }}"
                                alt="{{ $c['testimonial']['name'] ?? '' }}"
                                class="h-12 w-12 rounded-full object-cover ring-2 ring-white/20"
                                loading="lazy"
                            >
                        </picture>
                    @endif
                    <div class="text-left">
                        @if (! empty($c['testimonial']['name']))
                            <div class="font-semibold text-white">{{ $c['testimonial']['name'] }}</div>
                        @endif
                        @if (! empty($c['testimonial']['role']))
                            <div class="text-sm text-white/50">{{ $c['testimonial']['role'] }}</div>
                        @endif
                    </div>
                </div>
            </div>
        </section>
    @endif

    {{-- ── REFLECTIE ──────────────────────────────────────────────── --}}
    @if (! empty($c['reflection']['body']))
        <section class="bg-[#0c0c10] py-20 md:py-28">
            <div data-reveal class="mx-auto max-w-3xl px-6">
                <p class="mb-3 text-xs font-semibold uppercase tracking-widest text-primary-400">Reflectie</p>
                <h2 class="text-3xl font-black tracking-tight text-white md:text-4xl">Waarom werkte dit?</h2>
                <div class="prose prose-invert mt-6 max-w-none prose-p:text-white/60 prose-p:leading-relaxed">
                    {!! nl2br(e($c['reflection']['body'])) !!}
                </div>

                @if (! empty($c['reflection']['website_url']))
                    <a
                        href="{{ $c['reflection']['website_url'] }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-6 inline-flex cursor-pointer items-center gap-1.5 text-sm font-semibold text-primary-400 transition-colors hover:text-primary-300"
                    >
                        Bekijk het project
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                    </a>
                @endif
            </div>
        </section>
    @endif

    {{-- ── CTA ────────────────────────────────────────────────────── --}}
    @if (! empty($c['cta']['title']))
        <section class="bg-[#050507] py-16 md:py-24">
            <div class="mx-auto max-w-5xl px-6">
                <div
                    class="relative overflow-hidden rounded-3xl px-8 py-16 text-center md:px-16 md:py-20"
                    style="background:#0c0c10; border:1px solid rgba(255,255,255,0.07);"
                >
                    <div class="absolute left-1/2 top-0 h-20 w-px -translate-x-1/2" style="background:linear-gradient(to bottom, transparent, rgba(34,211,238,0.4), transparent);"></div>
                    <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full blur-[80px]" style="background:rgba(124,58,237,0.10);"></div>
                    <div class="pointer-events-none absolute -bottom-24 -left-24 h-64 w-64 rounded-full blur-[80px]" style="background:rgba(34,211,238,0.06);"></div>

                    <h2 class="relative text-3xl font-black leading-tight tracking-tight text-white md:text-4xl lg:text-5xl">
                        {{ $c['cta']['title'] }}
                    </h2>

                    @if (! empty($c['cta']['body']))
                        <p class="relative mx-auto mt-5 max-w-lg leading-relaxed text-white/50">
                            {{ $c['cta']['body'] }}
                        </p>
                    @endif

                    @if (! empty($c['cta']['button_label']) && ! empty($c['cta']['button_url']))
                        <div class="relative mt-10 flex flex-wrap justify-center gap-4">
                            <a
                                href="{{ $c['cta']['button_url'] }}"
                                class="cursor-pointer inline-flex items-center gap-2 rounded-full bg-white px-8 py-4 text-base font-semibold text-black transition-all hover:bg-white/90"
                            >
                                {{ $c['cta']['button_label'] }}
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </section>
    @endif

</x-layouts.site>
