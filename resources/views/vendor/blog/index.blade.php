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
    {{-- Hero header --}}
    <section class="relative overflow-hidden bg-[#050507] pt-10 md:pt-16 lg:pt-20">
        <div
            class="absolute inset-0 opacity-100"
            style="background-image: linear-gradient(rgba(255,255,255,.025) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.025) 1px, transparent 1px); background-size: 60px 60px;"
        ></div>
        <div class="pointer-events-none absolute -right-48 -top-48 h-[600px] w-[600px] animate-glow-drift rounded-full bg-cyan-400/[0.08] blur-[120px]"></div>
        <div class="pointer-events-none absolute -bottom-32 -left-48 h-[500px] w-[500px] animate-glow-drift-alt rounded-full bg-primary-600/[0.10] blur-[120px]"></div>

        <div class="relative mx-auto max-w-6xl px-6 py-24 md:py-32">
            <div class="mx-auto max-w-2xl text-center">
                <div class="mb-6 inline-flex animate-hero-enter items-center gap-2 rounded-full border border-cyan-400/20 bg-cyan-400/[0.07] px-4 py-1.5">
                    <span class="h-1.5 w-1.5 rounded-full bg-cyan-400"></span>
                    <span class="text-xs font-semibold tracking-wider text-cyan-400">Inzichten &amp; tips</span>
                </div>
                <h1 class="animate-hero-enter text-5xl font-black leading-[1.05] tracking-[-0.02em] [animation-delay:110ms] md:text-6xl">
                    <span class="bg-gradient-to-b from-white to-white/50 bg-clip-text text-transparent">Artikels</span>
                </h1>
                <p class="mx-auto mt-6 max-w-xl animate-hero-enter text-lg leading-relaxed text-white/55 [animation-delay:210ms]">
                    Praktische inzichten over websites, AI-tools en digitale groei — voor ondernemers die slim willen werken.
                </p>
            </div>
        </div>
    </section>

    {{-- Blog grid --}}
    <section class="bg-[#050507] py-20">
        <div class="mx-auto max-w-6xl px-6">
            @if ($posts->isEmpty())
                <p class="py-20 text-center text-white/40">Nog geen gepubliceerde artikels.</p>
            @else
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($posts as $index => $post)
                        <a
                            href="{{ $post->publicUrl() }}"
                            data-reveal
                            style="animation-delay: {{ min($index, 5) * 70 }}ms"
                            class="group relative flex flex-col overflow-hidden rounded-2xl border border-white/[0.08] bg-white/[0.04] backdrop-blur-sm transition-all duration-300 hover:border-white/[0.14] hover:bg-white/[0.06] hover:shadow-[0_0_0_1px_rgba(34,211,238,0.1),0_8px_40px_rgba(34,211,238,0.04)]"
                        >
                            @if ($post->featured)
                                <div class="absolute right-3 top-3 z-10 rounded-full bg-primary-600/90 px-2.5 py-0.5 text-xs font-semibold text-white backdrop-blur-sm">
                                    Uitgelicht
                                </div>
                            @endif

                            {{-- Cover-afbeelding --}}
                            @if ($post->cover_url)
                                <div class="aspect-video w-full overflow-hidden">
                                    <picture>
                                        <source srcset="{{ $post->cover_url }}" type="image/webp">
                                        <img
                                            src="{{ $post->cover_url }}"
                                            alt="{{ $post->cover_alt ?: $post->title }}"
                                            class="h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.02]"
                                        >
                                    </picture>
                                </div>
                            @else
                                <div class="aspect-video w-full bg-gradient-to-br from-primary-600/20 to-cyan-400/10"></div>
                            @endif

                            <div class="flex flex-1 flex-col p-6">
                                {{-- Tags --}}
                                @if (! empty($post->tags))
                                    <div class="mb-3 flex flex-wrap gap-1.5">
                                        @foreach ($post->tags as $tag)
                                            <span class="rounded-full border border-cyan-400/20 bg-cyan-400/[0.07] px-2.5 py-0.5 text-xs font-semibold text-cyan-400">{{ $tag }}</span>
                                        @endforeach
                                    </div>
                                @endif

                                <h2 class="text-lg font-bold leading-snug text-white line-clamp-2">{{ $post->title }}</h2>

                                {{-- Meta: datum + leestijd --}}
                                <div class="mt-2 flex items-center gap-3 text-xs text-white/35">
                                    @if ($post->published_at)
                                        <span>{{ $post->published_at->translatedFormat('j F Y') }}</span>
                                        <span>·</span>
                                    @endif
                                    <span>{{ $post->readingTimeMinutes() }} min leestijd</span>
                                </div>

                                @if ($post->excerpt)
                                    <p class="mt-3 flex-1 text-sm leading-relaxed text-white/50 line-clamp-3">{{ $post->excerpt }}</p>
                                @endif

                                <div class="mt-5 flex items-center gap-1.5 text-sm font-semibold text-white/50 transition-colors group-hover:text-white">
                                    Lees artikel
                                    <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </section>
</x-layouts.site>
