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
    @php
        // Verwerk de body: voeg id-attributen toe aan h2-headings voor de TOC
        // en stel de inhoudsopgave samen — alles server-side voor SEO-bots.
        $body = $post->body ?? '';
        $headings = [];
        $processedBody = preg_replace_callback(
            '/<h2([^>]*)>(.*?)<\/h2>/si',
            function ($m) use (&$headings) {
                $text = strip_tags($m[2]);
                $id   = \Illuminate\Support\Str::slug($text);
                // Zorg dat dubbele headings unieke IDs krijgen
                $count = count(array_filter($headings, fn ($h) => str_starts_with($h['id'], $id)));
                if ($count > 0) {
                    $id .= '-' . ($count + 1);
                }
                $headings[] = ['id' => $id, 'text' => $text];
                return '<h2' . $m[1] . ' id="' . e($id) . '">' . $m[2] . '</h2>';
            },
            $body,
        );
    @endphp

    {{-- Leesvoortgangsbalk (zie resources/js/reveal.js: initReadingProgress) --}}
    <div class="fixed inset-x-0 top-0 z-[60] h-[3px] bg-transparent">
        <div data-reading-progress class="h-full w-0 bg-gradient-to-r from-primary-500 to-cyan-400 transition-[width] duration-100 ease-out"></div>
    </div>

    @auth
        <a
            href="{{ route('filament.admin.resources.posts.edit', ['record' => $post, 'tab' => 'sections']) }}"
            class="fixed right-4 top-20 z-50 flex h-10 w-10 items-center justify-center rounded-full bg-violet-600 text-white shadow-lg transition hover:bg-violet-700"
            title="Bewerk dit artikel"
            aria-label="Bewerk artikel"
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
                href="{{ route('blog.index') }}"
                class="mb-8 inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-white/40 transition-colors hover:text-white/70"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
                Terug naar blog
            </a>

            {{-- Tags --}}
            @if (! empty($post->tags))
                <div class="mb-5 flex flex-wrap gap-2">
                    @foreach ($post->tags as $tag)
                        <span class="rounded-full border border-cyan-400/20 bg-cyan-400/[0.07] px-3 py-1 text-xs font-semibold text-cyan-400">
                            {{ $tag }}
                        </span>
                    @endforeach
                </div>
            @endif

            {{-- Titel --}}
            <h1 class="text-4xl font-black leading-[1.08] tracking-[-0.02em] text-white md:text-5xl lg:text-6xl">
                {{ $post->title }}
            </h1>

            {{-- Intro / excerpt --}}
            @if ($post->excerpt)
                <p class="mt-5 max-w-2xl text-lg leading-relaxed text-white/50">
                    {{ $post->excerpt }}
                </p>
            @endif

            {{-- Meta-balk: auteur + datum + leestijd --}}
            <div class="mt-8 flex flex-wrap items-center gap-4 border-t border-white/[0.08] pt-6">
                @if ($post->author_avatar_url)
                    <picture>
                        <source srcset="{{ $post->author_avatar_url }}" type="image/webp">
                        <img
                            src="{{ $post->author_avatar_url }}"
                            alt="{{ $post->author_name }}"
                            class="h-10 w-10 rounded-full object-cover ring-2 ring-white/10"
                        >
                    </picture>
                @else
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary-600/20 ring-2 ring-primary-500/20">
                        <span class="text-sm font-bold text-primary-400">
                            {{ mb_substr($post->author_name, 0, 1) }}
                        </span>
                    </div>
                @endif
                <div>
                    <div class="text-sm font-semibold text-white/80">{{ $post->author_name }}</div>
                    <div class="mt-0.5 flex items-center gap-2 text-xs text-white/35">
                        @if ($post->published_at)
                            <span>{{ $post->published_at->translatedFormat('j F Y') }}</span>
                            <span>·</span>
                        @endif
                        <span>{{ $post->readingTimeMinutes() }} min leestijd</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Cover-afbeelding --}}
        @if ($post->cover_url)
            <div class="relative mx-auto max-w-5xl px-6 pb-0">
                <div class="overflow-hidden rounded-t-2xl border border-b-0 border-white/[0.08]">
                    <picture>
                        <source srcset="{{ $post->cover_url }}" type="image/webp">
                        <img
                            src="{{ $post->cover_url }}"
                            alt="{{ $post->cover_alt ?: $post->title }}"
                            class="w-full origin-center animate-image-zoom-out object-cover"
                            fetchpriority="high"
                        >
                    </picture>
                </div>
            </div>
        @endif
    </section>

    {{-- ── ARTIKEL + INHOUDSOPGAVE ──────────────────────────────── --}}
    <section class="bg-[#050507] py-16 md:py-24">
        <div class="mx-auto max-w-6xl px-6">
            <div class="grid gap-12 lg:grid-cols-[1fr_280px]">

                {{-- Artikeltekst --}}
                <div id="article-body" data-reading-progress-target class="min-w-0">
                    @if ($processedBody)
                        <div class="
                            prose prose-invert max-w-none

                            prose-headings:font-black prose-headings:tracking-tight prose-headings:text-white
                            prose-h2:mt-14 prose-h2:mb-4 prose-h2:text-2xl prose-h2:md:text-3xl
                            prose-h3:mt-8 prose-h3:mb-3 prose-h3:text-xl

                            prose-p:text-white/65 prose-p:leading-relaxed prose-p:text-base

                            prose-a:text-primary-400 prose-a:no-underline prose-a:font-medium
                            hover:prose-a:text-primary-300

                            prose-strong:text-white prose-strong:font-semibold

                            prose-ul:text-white/65 prose-ol:text-white/65
                            prose-li:my-1

                            prose-hr:border-white/[0.08]

                            [&_blockquote]:not-italic
                            [&_blockquote]:my-10
                            [&_blockquote]:rounded-xl
                            [&_blockquote]:border-l-4
                            [&_blockquote]:border-primary-500
                            [&_blockquote]:bg-white/[0.03]
                            [&_blockquote]:px-6
                            [&_blockquote]:py-5
                            [&_blockquote]:text-lg
                            [&_blockquote]:font-medium
                            [&_blockquote]:leading-relaxed
                            [&_blockquote]:text-white/80
                            [&_blockquote_p]:my-0
                            [&_blockquote_p]:text-white/80
                        ">
                            {!! $processedBody !!}
                        </div>
                    @endif

                    {{-- Auteurblok --}}
                    <div class="mt-16 rounded-2xl border border-white/[0.08] bg-white/[0.03] p-6">
                        <p class="mb-4 text-xs font-semibold uppercase tracking-widest text-white/25">Over de auteur</p>
                        <div class="flex items-start gap-4">
                            @if ($post->author_avatar_url)
                                <picture>
                                    <source srcset="{{ $post->author_avatar_url }}" type="image/webp">
                                    <img
                                        src="{{ $post->author_avatar_url }}"
                                        alt="{{ $post->author_name }}"
                                        class="h-14 w-14 shrink-0 rounded-full object-cover ring-2 ring-white/10"
                                        loading="lazy"
                                    >
                                </picture>
                            @else
                                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-primary-600/20 ring-2 ring-primary-500/20">
                                    <span class="text-lg font-black text-primary-400">
                                        {{ mb_substr($post->author_name, 0, 1) }}
                                    </span>
                                </div>
                            @endif
                            <div>
                                <div class="font-bold text-white">{{ $post->author_name }}</div>
                                @if ($post->author_bio)
                                    <p class="mt-1 text-sm leading-relaxed text-white/50">{{ $post->author_bio }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Sticky sidebar: inhoudsopgave --}}
                @if (count($headings) >= 2)
                    <aside class="hidden lg:block">
                        <div class="sticky top-24 rounded-xl border border-white/[0.08] bg-white/[0.03] p-5">
                            <p class="mb-4 text-xs font-semibold uppercase tracking-widest text-white/30">In dit artikel</p>
                            <nav>
                                <ol class="space-y-2">
                                    @foreach ($headings as $heading)
                                        <li>
                                            <a
                                                href="#{{ $heading['id'] }}"
                                                class="block cursor-pointer text-sm leading-snug text-white/45 transition-colors hover:text-white/80"
                                            >
                                                {{ $heading['text'] }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ol>
                            </nav>
                        </div>
                    </aside>
                @endif
            </div>
        </div>
    </section>

    {{-- ── CTA BANNER ────────────────────────────────────────────── --}}
    <section class="bg-[#050507] pb-16 md:pb-24">
        <div class="mx-auto max-w-5xl px-6">
            <div
                class="relative overflow-hidden rounded-3xl px-8 py-16 text-center md:px-16 md:py-20"
                style="background:#0c0c10; border:1px solid rgba(255,255,255,0.07);"
            >
                <div class="absolute left-1/2 top-0 h-20 w-px -translate-x-1/2" style="background:linear-gradient(to bottom, transparent, rgba(34,211,238,0.4), transparent);"></div>
                <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full blur-[80px]" style="background:rgba(124,58,237,0.10);"></div>
                <div class="pointer-events-none absolute -bottom-24 -left-24 h-64 w-64 rounded-full blur-[80px]" style="background:rgba(34,211,238,0.06);"></div>

                <h2 class="relative text-3xl font-black leading-tight tracking-tight text-white md:text-4xl lg:text-5xl">
                    Benieuwd welke tools jou tijd kunnen besparen?
                </h2>
                <p class="relative mx-auto mt-5 max-w-lg leading-relaxed text-white/50">
                    Plan een gratis gesprek en ontdek hoe je met slimme AI-tools uren per week terugwint.
                </p>
                <div class="relative mt-10 flex flex-wrap justify-center gap-4">
                    <a
                        href="/contact"
                        class="cursor-pointer inline-flex items-center gap-2 rounded-full bg-white px-8 py-4 text-base font-semibold text-black transition-all hover:bg-white/90"
                    >
                        Plan jouw adviesgesprek
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- ── GERELATEERDE ARTIKELS ──────────────────────────────────── --}}
    @if ($related->isNotEmpty())
        <section class="bg-[#0c0c10] py-16 md:py-24">
            <div class="mx-auto max-w-6xl px-6">
                <h2 class="mb-10 text-2xl font-black tracking-tight text-white">Gerelateerde artikels</h2>
                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($related as $index => $rel)
                        <a
                            href="{{ $rel->publicUrl() }}"
                            data-reveal
                            style="animation-delay: {{ min($index, 5) * 70 }}ms"
                            class="group flex flex-col rounded-xl border border-white/[0.08] bg-white/[0.03] p-5 transition-all hover:border-white/[0.14] hover:bg-white/[0.06]"
                        >
                            @if (! empty($rel->tags))
                                <div class="mb-3 flex flex-wrap gap-1.5">
                                    @foreach ($rel->tags as $tag)
                                        <span class="rounded-full border border-cyan-400/20 bg-cyan-400/[0.07] px-2.5 py-0.5 text-xs font-semibold text-cyan-400">{{ $tag }}</span>
                                    @endforeach
                                </div>
                            @endif
                            <h3 class="flex-1 text-base font-bold leading-snug text-white line-clamp-2">{{ $rel->title }}</h3>
                            <div class="mt-3 flex items-center gap-2 text-xs text-white/35">
                                @if ($rel->published_at)
                                    <span>{{ $rel->published_at->translatedFormat('j F Y') }}</span>
                                    <span>·</span>
                                @endif
                                <span>{{ $rel->readingTimeMinutes() }} min</span>
                            </div>
                            <div class="mt-4 flex items-center gap-1 text-xs font-semibold text-white/40 transition-colors group-hover:text-white/70">
                                Lees artikel
                                <svg class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

</x-layouts.site>
